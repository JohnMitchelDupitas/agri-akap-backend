<?php

namespace App\Http\Controllers;

use App\Models\FarmPlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmPlotController extends Controller
{
    /**
     * List farm plots, optionally scoped to a single farmer.
     * Powers the damage-assessment plot picker and offline caching.
     */
    public function index(Request $request): JsonResponse
    {
        $plots = FarmPlot::with('farmer:id,first_name,surname,rsbsa_no,permanent_brgy')
            ->when($request->filled('farmer_id'), function ($query) use ($request) {
                $query->where('farmer_id', $request->query('farmer_id'));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Farm plots retrieved.',
            'data' => $plots,
        ], 200);
    }

    /**
     * Show a single farm plot with its owner.
     */
    public function show(string $id): JsonResponse
    {
        $plot = FarmPlot::with('farmer')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $plot,
        ], 200);
    }
}
