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
        $technicianId = $request->user()->id; // The logged-in field worker

        try {
            // DB::transaction with pessimistic locking prevents race conditions
            return DB::transaction(function () use ($validated, $technicianId) {

                // 1. Lock the Program row so no other technician can modify inventory at this exact millisecond
                $program = Program::where('id', $validated['program_id'])
                    ->lockForUpdate()
                    ->first();

                // 2. Validate Program Status
                if (!$program->is_active || $program->end_date < now()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This program is inactive or has already ended.'
                    ], 400);
                }

                // 3. Double-Dipping Check: Has this farmer already claimed for THIS program?
                $existingClaim = Distribution::where('farmer_id', $validated['farmer_id'])
                    ->where('program_id', $program->id)
                    ->first();

                if ($existingClaim) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'FRAUD ALERT: This farmer has already claimed their subsidy for this program.',
                        'data' => [
                            'claimed_at' => $existingClaim->created_at->format('M d, Y h:i A')
                        ]
                    ], 403); // 403 Forbidden
                }

                // 4. Fetch Farmer and Calculate Eligible Hectares
                $farmer = Farmer::with('farmPlots')->findOrFail($validated['farmer_id']);

                // Sum the size of all plots the farmer owns
                $totalHectares = $farmer->farmPlots->sum('size_ha');

                if ($totalHectares <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Farmer has no valid farm plots registered. Cannot allocate subsidy.'
                    ], 400);
                }

                // Cap the calculation based on the Program's maximum allowed hectares
                $eligibleHectares = min($totalHectares, $program->max_hectare_cap);

                // Calculate the final quantity: (Eligible Hectares * Allocation per Hectare)
                // We use floor() or ceil() depending on DA rounding rules. Let's round down to nearest whole bag.
                $quantityToDispense = floor($eligibleHectares * $program->per_hectare_allocation);

                if ($quantityToDispense < 1) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Calculated allocation is less than 1 unit. Farmer farm size does not meet minimum requirements.'
                    ], 400);
                }

                // 5. Inventory Verification
                if ($program->remaining_quantity < $quantityToDispense) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient inventory. The system calculated ' . $quantityToDispense . ' units, but only ' . $program->remaining_quantity . ' remain.'
                    ], 400);
                }

                // 6. Process the Transaction
                // Deduct from warehouse
                $program->remaining_quantity -= $quantityToDispense;
                $program->save();

                // Create the audit trail record
                $distribution = Distribution::create([
                    'program_id' => $program->id,
                    'farmer_id' => $farmer->id,
                    'distributed_by' => $technicianId, // Ties the action to the exact staff member
                    'quantity_claimed' => $quantityToDispense,
                    'status' => 'claimed',
                    'claimed_at' => now()
                ]);

                // 7. Return the absolute Success Payload
                return response()->json([
                    'status' => 'success',
                    'message' => 'Verification Passed. Subsidy successfully claimed.',
                    'data' => [
                        'farmer_name' => $farmer->first_name . ' ' . $farmer->surname,
                        'total_farm_size' => $totalHectares . ' ha',
                        'eligible_size_capped' => $eligibleHectares . ' ha',
                        'quantity_dispensed' => $quantityToDispense . ' ' . $program->unit_of_measurement,
                        'inventory_remaining' => $program->remaining_quantity
                    ]
                ], 200);

            }); // End Transaction

        } catch (\Exception $e) {
            Log::error('Distribution Sync Failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'A critical error occurred while processing the claim.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
