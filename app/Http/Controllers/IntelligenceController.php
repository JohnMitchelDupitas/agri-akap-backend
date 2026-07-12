<?php

namespace App\Http\Controllers;

use App\Models\CropMonitoring;
use App\Models\FarmPlot;
use App\Models\PestOutbreak;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class IntelligenceController extends Controller
{
    /**
     * Log a new crop cycle and check for Monoculture risks.
     */
    public function logCrop(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_plot_id' => 'required|exists:farm_plots,id',
            'crop_planted' => 'required|string|max:100',
            'season' => 'required|in:Wet,Dry',
            'year' => 'required|integer|min:2000|max:2100',
            'soil_ph' => 'nullable|numeric|between:0,14',
            'area_planted_ha' => 'nullable|numeric|min:0|max:100000',
            'expected_yield_kg' => 'nullable|numeric|min:0|max:100000000',
            'actual_yield_kg' => 'nullable|numeric|min:0|max:100000000',
            'crop_stage' => 'nullable|string|max:50',
        ]);

        $validated['technician_id'] = $request->user()->id;
        $currentLog = CropMonitoring::create($validated);

        // Monoculture algorithm: fetch the last 3 plantings for this plot.
        $history = CropMonitoring::where('farm_plot_id', $validated['farm_plot_id'])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->pluck('crop_planted')
            ->toArray();

        $monocultureWarning = count($history) === 3 && count(array_unique($history)) === 1;

        return response()->json([
            'status' => 'success',
            'message' => 'Crop cycle logged successfully.',
            'data' => $currentLog->load('farmPlot:id,location_brgy,commodity'),
            'monoculture_alert' => $monocultureWarning,
            'alert_message' => $monocultureWarning
                ? 'WARNING: This plot has planted ' . $validated['crop_planted'] . ' for 3 consecutive seasons. Soil depletion risk is high. Recommend crop rotation.'
                : null,
        ], 201);
    }

    /**
     * Crop history for a specific plot (for the intelligence dashboard).
     */
    public function cropHistory(Request $request): JsonResponse
    {
        $request->validate(['farm_plot_id' => 'required|exists:farm_plots,id']);

        $history = CropMonitoring::with('technician:id,name')
            ->where('farm_plot_id', $request->query('farm_plot_id'))
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }

    /**
     * Plots at monoculture risk (planted the same crop 3+ consecutive times).
     * Powers the crop rotation alert panel in the admin intelligence dashboard.
     */
    public function monocultureAlerts(): JsonResponse
    {
        $plots = FarmPlot::with('farmer:id,first_name,surname,permanent_brgy')->get();

        $alerts = [];
        foreach ($plots as $plot) {
            $history = CropMonitoring::where('farm_plot_id', $plot->id)
                ->orderBy('year', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->pluck('crop_planted')
                ->toArray();

            if (count($history) >= 3 && count(array_unique($history)) === 1) {
                $alerts[] = [
                    'farm_plot_id' => $plot->id,
                    'location_brgy' => $plot->location_brgy,
                    'commodity' => $plot->commodity,
                    'size_ha' => $plot->size_ha,
                    'farmer' => $plot->farmer,
                    'repeated_crop' => $history[0],
                    'consecutive_seasons' => count($history),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $alerts,
        ]);
    }

    /**
     * Fetch the Intelligence Dashboard Data (pest outbreaks + monoculture summary).
     */
    public function getDashboardData(): JsonResponse
    {
        $activePests = PestOutbreak::with([
            'farmPlot:id,location_brgy,commodity,farmer_id',
            'farmPlot.farmer:id,first_name,surname',
            'technician:id,name',
        ])
            ->where('status', 'Active')
            ->orderBy('date_spotted', 'desc')
            ->get();

        $pestSummary = [
            'total' => PestOutbreak::count(),
            'active' => PestOutbreak::where('status', 'Active')->count(),
            'contained' => PestOutbreak::where('status', 'Contained')->count(),
            'resolved' => PestOutbreak::where('status', 'Resolved')->count(),
            'by_severity' => PestOutbreak::where('status', 'Active')
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_pests' => $activePests,
                'pest_summary' => $pestSummary,
            ],
        ], 200);
    }

    /**
     * Log a new pest sighting from the field. Requires geolocation.
     */
    public function reportPest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_plot_id' => 'required|exists:farm_plots,id',
            'pest_name' => 'required|string|max:150',
            'severity' => 'required|in:Low,Medium,High,Critical',
            'date_spotted' => 'required|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['technician_id'] = $request->user()->id;
        $validated['status'] = 'Active';

        $pest = PestOutbreak::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pest outbreak reported. MAO office has been notified.',
            'data' => $pest->load('farmPlot:id,location_brgy,commodity'),
        ], 201);
    }

    /**
     * Update a pest outbreak status (Contained / Resolved).
     */
    public function updatePestStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Contained', 'Resolved'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $pest = PestOutbreak::findOrFail($id);
        $pest->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Pest status updated to {$validated['status']}.",
            'data' => $pest->fresh(),
        ]);
    }
}
