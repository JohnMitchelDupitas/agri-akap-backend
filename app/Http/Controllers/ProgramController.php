<?php

namespace App\Http\Controllers;

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
        $programs = Program::orderBy('created_at', 'desc')
            ->when($request->query('active_only'), function ($query) {
                return $query->where('is_active', true)->where('end_date', '>=', now());
            })
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidies and program registries loaded.',
            'data' => $programs
        ], 200);
    }

    /**
     * Initialize a new subsidy distribution campaign with protected inventory allocations.
     */
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        
        // On initialization, remaining quantity completely matches total drop-off quantity
        $validatedData['remaining_quantity'] = $validatedData['total_quantity'];
        $validatedData['is_active'] = true;

        $program = Program::create($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Subsidy campaign initialized and inventory secured.',
            'data' => $program
        ], 201);
    }

    /**
     * Show a specific program profile with real-time remaining inventory counts.
     */
    public function show(string $id): JsonResponse
    {
        $program = Program::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Program metadata fetched.',
            'data' => $program
        ], 200);
    }
}