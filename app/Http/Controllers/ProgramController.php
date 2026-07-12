<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Program;
use App\Http\Requests\StoreProgramRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /**
     * Display current active and past assistance programs.
     */
    public function index(Request $request): JsonResponse
    {
        $programs = Program::withCount('distributions')
            ->orderBy('created_at', 'desc')
            ->when($request->boolean('active_only'), fn ($q) =>
                $q->where('is_active', true)->where('end_date', '>=', now())
            )
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidies and program registries loaded.',
            'data' => $programs,
        ], 200);
    }

    /**
     * Initialize a new subsidy distribution campaign with protected inventory allocations.
     */
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $validatedData['remaining_quantity'] = $validatedData['total_quantity'];
        $validatedData['is_active'] = true;

        $program = Program::create($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidy campaign initialized and inventory secured.',
            'data' => $program,
        ], 201);
    }

    /**
     * Show a specific program profile with real-time inventory and distribution summary.
     */
    public function show(string $id): JsonResponse
    {
        $program = Program::withCount('distributions')->findOrFail($id);

        $summary = Distribution::where('program_id', $id)
            ->selectRaw('SUM(quantity_claimed) as total_dispensed, COUNT(*) as beneficiaries')
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Program metadata fetched.',
            'data' => array_merge($program->toArray(), [
                'total_dispensed' => $summary->total_dispensed ?? 0,
                'beneficiaries' => $summary->beneficiaries ?? 0,
            ]),
        ], 200);
    }

    /**
     * Deactivate a program (admin only). Does not delete — preserves audit trail.
     */
    public function deactivate(string $id): JsonResponse
    {
        $program = Program::findOrFail($id);

        if (!$program->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program is already inactive.',
            ], 409);
        }

        $program->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Program has been deactivated. Existing distributions are preserved.',
            'data' => $program->fresh(),
        ]);
    }
}
