<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\Program;
use App\Http\Requests\ClaimSubsidyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributionController extends Controller
{
    /**
     * Verify eligibility, calculate allocation, and process the subsidy claim.
     */
    public function processClaim(ClaimSubsidyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $technicianId = $request->user()->id;

        $result = $this->executeClaim($validated, $technicianId);

        return response()->json($result['body'], $result['http']);
    }

    /**
     * Core claim engine. Returns a structured result usable by both the
     * live HTTP endpoint and the offline bulk-sync engine.
     *
     * @return array{http:int, outcome:string, body:array}
     *   outcome is one of: synced | duplicate | failed
     */
    public function executeClaim(array $validated, string $technicianId): array
    {
        try {
            return DB::transaction(function () use ($validated, $technicianId) {

                // Idempotency guard: a client-generated UUID that already
                // exists means this offline record was already synced.
                if (!empty($validated['client_id'])) {
                    $already = Distribution::find($validated['client_id']);
                    if ($already) {
                        return $this->claimResult(200, 'duplicate', [
                            'status' => 'success',
                            'message' => 'This distribution was already synced.',
                            'data' => $already,
                        ]);
                    }
                }

                // 1. Lock the Program row to prevent inventory race conditions.
                $program = Program::where('id', $validated['program_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$program) {
                    return $this->claimResult(404, 'failed', [
                        'status' => 'error',
                        'message' => 'Program not found.',
                    ]);
                }

                // 2. Validate Program Status
                if (!$program->is_active || $program->end_date < now()) {
                    return $this->claimResult(400, 'failed', [
                        'status' => 'error',
                        'message' => 'This program is inactive or has already ended.',
                    ]);
                }

                // 3. Double-Dipping Check
                $existingClaim = Distribution::where('farmer_id', $validated['farmer_id'])
                    ->where('program_id', $program->id)
                    ->first();

                if ($existingClaim) {
                    // Treat as a resolved duplicate for offline sync so the
                    // client can safely clear the queued item.
                    return $this->claimResult(409, 'duplicate', [
                        'status' => 'error',
                        'message' => 'FRAUD ALERT: This farmer has already claimed their subsidy for this program.',
                        'data' => [
                            'claimed_at' => $existingClaim->created_at->format('M d, Y h:i A'),
                        ],
                    ]);
                }

                // 4. Fetch Farmer and Calculate Eligible Hectares
                $farmer = Farmer::with('farmPlots')->findOrFail($validated['farmer_id']);
                $totalHectares = $farmer->farmPlots->sum('size_ha');

                if ($totalHectares <= 0) {
                    return $this->claimResult(400, 'failed', [
                        'status' => 'error',
                        'message' => 'Farmer has no valid farm plots registered. Cannot allocate subsidy.',
                    ]);
                }

                $eligibleHectares = min($totalHectares, $program->max_hectare_cap);
                $quantityToDispense = floor($eligibleHectares * $program->per_hectare_allocation);

                if ($quantityToDispense < 1) {
                    return $this->claimResult(400, 'failed', [
                        'status' => 'error',
                        'message' => 'Calculated allocation is less than 1 unit. Farmer farm size does not meet minimum requirements.',
                    ]);
                }

                // 5. Inventory Verification
                if ($program->remaining_quantity < $quantityToDispense) {
                    return $this->claimResult(400, 'failed', [
                        'status' => 'error',
                        'message' => 'Insufficient inventory. The system calculated ' . $quantityToDispense . ' units, but only ' . $program->remaining_quantity . ' remain.',
                    ]);
                }

                // 6. Process the Transaction
                $program->remaining_quantity -= $quantityToDispense;
                $program->save();

                $distribution = Distribution::create([
                    'id' => $validated['client_id'] ?? null,
                    'program_id' => $program->id,
                    'farmer_id' => $farmer->id,
                    'distributed_by' => $technicianId,
                    'quantity_claimed' => $quantityToDispense,
                    'status' => 'claimed',
                    'device_id' => $validated['device_id'] ?? null,
                    'claimed_at' => $validated['claimed_at'] ?? now(),
                ]);

                return $this->claimResult(200, 'synced', [
                    'status' => 'success',
                    'message' => 'Verification Passed. Subsidy successfully claimed.',
                    'data' => [
                        'id' => $distribution->id,
                        'farmer_name' => $farmer->first_name . ' ' . $farmer->surname,
                        'total_farm_size' => $totalHectares . ' ha',
                        'eligible_size_capped' => $eligibleHectares . ' ha',
                        'quantity_dispensed' => $quantityToDispense . ' ' . $program->unit_of_measurement,
                        'inventory_remaining' => $program->remaining_quantity,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Distribution claim failed: ' . $e->getMessage());

            return $this->claimResult(500, 'failed', [
                'status' => 'error',
                'message' => 'A critical error occurred while processing the claim.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function claimResult(int $http, string $outcome, array $body): array
    {
        return ['http' => $http, 'outcome' => $outcome, 'body' => $body];
    }
}
