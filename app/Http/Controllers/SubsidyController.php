<?php

namespace App\Http\Controllers;

use App\Models\SubsidyProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubsidyController extends Controller
{
    /**
     * List subsidy programs with beneficiary counts for the admin console.
     */
    public function index(): JsonResponse
    {
        $programs = SubsidyProgram::query()
            ->withCount([
                'beneficiaries',
                'beneficiaries as claimed_count' => fn ($q) => $q->where('status', 'Claimed'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SubsidyProgram $p) => [
                'id' => $p->id,
                'program_name' => $p->program_name,
                'target_crop' => $p->target_crop,
                'max_hectares_limit' => (float) $p->max_hectares_limit,
                'items_per_hectare' => (int) $p->items_per_hectare,
                'status' => $p->status,
                'unit_of_measurement' => $p->unit_of_measurement,
                'total_quantity' => (int) $p->total_quantity,
                'remaining_quantity' => (int) $p->remaining_quantity,
                'reorder_level' => $p->reorder_level !== null ? (int) $p->reorder_level : null,
                'is_low_stock' => $p->reorder_level !== null && $p->remaining_quantity <= $p->reorder_level,
                'beneficiaries_count' => (int) $p->beneficiaries_count,
                'claimed_count' => (int) $p->claimed_count,
                'created_at' => optional($p->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidy programs loaded.',
            'data' => $programs,
        ]);
    }

    /**
     * Create a new subsidy program (Draft by default).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_name' => 'required|string|max:255',
            'target_crop' => ['required', Rule::in(['Rice', 'Corn'])],
            'max_hectares_limit' => 'required|numeric|min:0.01|max:9999',
            'items_per_hectare' => 'required|integer|min:1|max:1000',
            'status' => ['nullable', Rule::in(['Draft', 'Active', 'Completed'])],
            'unit_of_measurement' => 'nullable|string|max:64',
            'total_quantity' => 'nullable|integer|min:0|max:1000000',
            'reorder_level' => 'nullable|integer|min:0|max:1000000',
        ]);

        $totalQuantity = $validated['total_quantity'] ?? 0;

        $program = SubsidyProgram::create([
            'program_name' => $validated['program_name'],
            'target_crop' => $validated['target_crop'],
            'max_hectares_limit' => $validated['max_hectares_limit'],
            'items_per_hectare' => $validated['items_per_hectare'],
            'status' => $validated['status'] ?? 'Draft',
            'unit_of_measurement' => $validated['unit_of_measurement'] ?? 'Bags',
            'total_quantity' => $totalQuantity,
            'remaining_quantity' => $totalQuantity,
            'reorder_level' => $validated['reorder_level'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidy program created.',
            'data' => $program,
        ], 201);
    }

    /**
     * Log an incoming warehouse delivery for one subsidy program (admin only).
     * Adds to both the lifetime total and the currently claimable stock.
     */
    public function restock(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity_added' => 'required|integer|min:1|max:1000000',
        ]);

        $program = DB::transaction(function () use ($id, $validated) {
            $program = SubsidyProgram::where('id', $id)->lockForUpdate()->firstOrFail();
            $program->total_quantity += $validated['quantity_added'];
            $program->remaining_quantity += $validated['quantity_added'];
            $program->save();

            return $program;
        });

        return response()->json([
            'status' => 'success',
            'message' => "Delivery logged. {$validated['quantity_added']} {$program->unit_of_measurement} added to stock.",
            'data' => $program->fresh(),
        ]);
    }

    /**
     * Update stock-management configuration (admin only): unit label and
     * minimum reorder threshold for low-stock warnings.
     */
    public function updateConfig(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'unit_of_measurement' => 'nullable|string|max:64',
            'reorder_level' => 'nullable|integer|min:0|max:1000000',
        ]);

        $program = SubsidyProgram::query()->findOrFail($id);
        $program->update([
            'unit_of_measurement' => $validated['unit_of_measurement'] ?? $program->unit_of_measurement,
            'reorder_level' => $validated['reorder_level'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Stock settings updated.',
            'data' => $program->fresh(),
        ]);
    }

    /**
     * Generate eligible beneficiaries from current, active planting records.
     */
    public function generateMasterlist(string $id): JsonResponse
    {
        $program = SubsidyProgram::query()->findOrFail($id);

        if ($program->status === 'Completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'A completed subsidy program cannot regenerate its masterlist.',
            ], 409);
        }

        $cropLike = '%' . strtolower($program->target_crop) . '%';
        [$plotArea, $plantArea] = $this->cropAreaSubqueries($cropLike);

        $skippedNoRsbsa = (int) DB::table('farmers')
            ->leftJoinSub($plotArea, 'plots', fn ($join) => $join->on('plots.farmer_id', '=', 'farmers.id'))
            ->leftJoinSub($plantArea, 'planted', fn ($join) => $join->on('planted.farmer_id', '=', 'farmers.id'))
            ->whereNull('farmers.deleted_at')
            ->where(function ($q) {
                $q->whereNull('farmers.rsbsa_no')->orWhere('farmers.rsbsa_no', '');
            })
            ->where(function ($q) {
                $q->whereNotNull('plots.area')->orWhereNotNull('planted.area');
            })
            ->count();

        $eligibleFarmers = DB::table('farmers')
            ->leftJoinSub($plotArea, 'plots', fn ($join) => $join->on('plots.farmer_id', '=', 'farmers.id'))
            ->leftJoinSub($plantArea, 'planted', fn ($join) => $join->on('planted.farmer_id', '=', 'farmers.id'))
            ->whereNull('farmers.deleted_at')
            ->whereNotNull('farmers.rsbsa_no')
            ->where('farmers.rsbsa_no', '!=', '')
            ->whereRaw('COALESCE(planted.area, plots.area, 0) > 0')
            ->select([
                'farmers.id',
                'farmers.rsbsa_no',
            ])
            ->selectRaw('COALESCE(planted.area, plots.area, 0) as farm_area')
            ->get();

        $now = now();
        $rows = $eligibleFarmers
            ->map(function ($farmer) use ($program, $now) {
                $eligibleArea = min(
                    (float) $farmer->farm_area,
                    (float) $program->max_hectares_limit
                );

                // Allocations are whole items; partial items are not distributable.
                $allocation = (int) floor(
                    ($eligibleArea * (int) $program->items_per_hectare) + 0.0000001
                );

                if ($allocation < 1) {
                    return null;
                }

                return [
                    'id' => (string) Str::uuid(),
                    'program_id' => $program->id,
                    'farmer_rsbsa_no' => $farmer->rsbsa_no,
                    'calculated_allocation' => $allocation,
                    'status' => 'Pending',
                    'claimed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $generatedCount = DB::transaction(function () use ($rows) {
            $inserted = 0;

            foreach (array_chunk($rows, 500) as $chunk) {
                $inserted += DB::table('tbl_subsidy_beneficiaries')->insertOrIgnore($chunk);
            }

            return $inserted;
        });

        $masterlistCount = DB::table('tbl_subsidy_beneficiaries')
            ->where('program_id', $program->id)
            ->count();

        $message = "{$generatedCount} new beneficiaries added to the masterlist.";
        if ($generatedCount === 0 && count($rows) === 0) {
            $message = $skippedNoRsbsa > 0
                ? "No eligible farmers found. {$skippedNoRsbsa} matching farmer(s) were skipped because they have no RSBSA number."
                : 'No eligible farmers found. Matching farmers need an RSBSA number plus a Rice/Corn farm plot or an active planting log.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'program_id' => $program->id,
                'eligible_count' => count($rows),
                'generated_count' => $generatedCount,
                'skipped_no_rsbsa' => $skippedNoRsbsa,
                'masterlist_count' => $masterlistCount,
            ],
        ]);
    }

    /**
     * Return a compact, spreadsheet-ready masterlist for one program.
     */
    public function masterlist(string $id): JsonResponse
    {
        $program = SubsidyProgram::query()->findOrFail($id);

        $cropLike = '%' . strtolower($program->target_crop) . '%';
        [$plotArea, $plantArea] = $this->cropAreaSubqueries($cropLike);

        $masterlist = DB::table('tbl_subsidy_beneficiaries as beneficiaries')
            ->join('farmers', 'farmers.rsbsa_no', '=', 'beneficiaries.farmer_rsbsa_no')
            ->leftJoinSub($plotArea, 'plots', fn ($join) => $join->on('plots.farmer_id', '=', 'farmers.id'))
            ->leftJoinSub($plantArea, 'planted', fn ($join) => $join->on('planted.farmer_id', '=', 'farmers.id'))
            ->where('beneficiaries.program_id', $program->id)
            ->whereNull('farmers.deleted_at')
            ->orderBy('farmers.surname')
            ->orderBy('farmers.first_name')
            ->select([
                'beneficiaries.id as beneficiary_id',
                'beneficiaries.farmer_rsbsa_no as rsbsa_no',
                'farmers.surname as last_name',
                'farmers.first_name',
                'farmers.permanent_brgy as barangay',
                'beneficiaries.calculated_allocation',
                'beneficiaries.status',
            ])
            ->selectRaw('ROUND(COALESCE(planted.area, plots.area, 0), 4) as farm_area')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidy masterlist loaded.',
            'data' => [
                'program' => [
                    'id' => $program->id,
                    'program_name' => $program->program_name,
                    'target_crop' => $program->target_crop,
                    'max_hectares_limit' => $program->max_hectares_limit,
                    'items_per_hectare' => $program->items_per_hectare,
                    'status' => $program->status,
                    'unit_of_measurement' => $program->unit_of_measurement,
                    'total_quantity' => (int) $program->total_quantity,
                    'remaining_quantity' => (int) $program->remaining_quantity,
                    'reorder_level' => $program->reorder_level !== null ? (int) $program->reorder_level : null,
                    'is_low_stock' => $program->reorder_level !== null && $program->remaining_quantity <= $program->reorder_level,
                ],
                'count' => $masterlist->count(),
                'masterlist' => $masterlist,
            ],
        ]);
    }

    /**
     * Mark one beneficiary as Claimed and deduct their allocation from the
     * program's warehouse stock (DA 6-step distribution: release = stock out).
     */
    public function claimBeneficiary(string $id, string $beneficiaryId): JsonResponse
    {
        $result = DB::transaction(function () use ($id, $beneficiaryId) {
            $program = SubsidyProgram::where('id', $id)->lockForUpdate()->firstOrFail();

            $beneficiary = DB::table('tbl_subsidy_beneficiaries')
                ->where('id', $beneficiaryId)
                ->where('program_id', $id)
                ->first();

            if (!$beneficiary) {
                return ['error' => 'Beneficiary not found on this program.', 'code' => 404];
            }

            if ($beneficiary->status === 'Claimed') {
                return ['error' => 'This beneficiary has already claimed their allocation.', 'code' => 409];
            }

            if ($program->remaining_quantity < $beneficiary->calculated_allocation) {
                return [
                    'error' => "Insufficient stock. Only {$program->remaining_quantity} {$program->unit_of_measurement} remaining, but this beneficiary is allocated {$beneficiary->calculated_allocation}. Log a delivery first.",
                    'code' => 409,
                ];
            }

            $program->remaining_quantity -= $beneficiary->calculated_allocation;
            $program->save();

            DB::table('tbl_subsidy_beneficiaries')
                ->where('id', $beneficiaryId)
                ->update(['status' => 'Claimed', 'claimed_at' => now(), 'updated_at' => now()]);

            return ['program' => $program->fresh()];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $result['error'],
            ], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Beneficiary marked as Claimed. Stock updated.',
            'data' => [
                'program' => $result['program'],
            ],
        ]);
    }

    /**
     * Crop-area subqueries: RSBSA farm plots + active planting logs.
     *
     * @return array{0: \Illuminate\Database\Query\Builder, 1: \Illuminate\Database\Query\Builder}
     */
    private function cropAreaSubqueries(string $cropLike): array
    {
        $plotArea = DB::table('farm_plots')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(commodity) like ?', [$cropLike])
            ->groupBy('farmer_id')
            ->select('farmer_id')
            ->selectRaw('SUM(size_ha) as area');

        $plantArea = DB::table('planting_logs')
            ->where('status', 'Active')
            ->whereRaw('LOWER(crop_type) like ?', [$cropLike])
            ->groupBy('farmer_id')
            ->select('farmer_id')
            ->selectRaw('SUM(area_planted) as area');

        return [$plotArea, $plantArea];
    }
}
