<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\PestMonitoring;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PestMonitoringController extends Controller
{
    use DecodesBase64Image;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barangay' => ['nullable', 'string'],
            'crop_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $user = $request->user();
        $query = PestMonitoring::query()
            ->with([
                'farmer:id,rsbsa_no,surname,first_name,middle_name,ext_name,birthdate,permanent_house_no,permanent_street,permanent_brgy,permanent_city,permanent_province',
                'farmPlot:id,location_brgy,commodity,size_ha',
            ])
            ->orderByDesc('date_of_inspection')
            ->orderByDesc('created_at');

        if ($user->role === 'barangay_official' && $user->assigned_barangay) {
            $query->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $user->assigned_barangay));
        } elseif (! empty($validated['barangay'])) {
            $query->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $validated['barangay']));
        }

        if (! empty($validated['crop_type'])) {
            $query->where('crop', $validated['crop_type']);
        }
        if (! empty($validated['date_from'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereDate('date_of_inspection', '>=', $validated['date_from'])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->whereNull('date_of_inspection')
                            ->whereDate('created_at', '>=', $validated['date_from']);
                    });
            });
        }
        if (! empty($validated['date_to'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereDate('date_of_inspection', '<=', $validated['date_to'])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->whereNull('date_of_inspection')
                            ->whereDate('created_at', '<=', $validated['date_to']);
                    });
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate((int) ($validated['per_page'] ?? 200)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'farmer_id' => ['required', 'uuid', 'exists:farmers,id'],
            'farm_plot_id' => ['nullable', 'uuid', 'exists:farm_plots,id'],
            'crop' => ['required', 'string', 'max:64'],
            'variety' => ['nullable', 'string', 'max:128'],
            'area_planted' => ['required', 'numeric', 'min:0'],
            'days_after_planting' => ['required', 'integer', 'min:0', 'max:400'],
            'area_damage_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'damage_by' => ['required', 'string', 'max:255'],
            'date_of_inspection' => ['required', 'date'],
            'farm_location' => ['nullable', 'string', 'max:255'],
            'photo_base64' => ['nullable', 'string'],
        ]);

        $farmer = Farmer::findOrFail($validated['farmer_id']);
        $user = $request->user();

        if ($user->role === 'barangay_official' && $user->assigned_barangay
            && $farmer->permanent_brgy !== $user->assigned_barangay) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only encode farmers from your assigned barangay.',
            ], 403);
        }

        if (! empty($validated['farm_plot_id'])) {
            $plot = FarmPlot::findOrFail($validated['farm_plot_id']);
            if ($plot->farmer_id !== $farmer->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected farm plot does not belong to this farmer.',
                ], 422);
            }
            if (strcasecmp((string) $plot->commodity, (string) $validated['crop']) !== 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Selected plot is {$plot->commodity}, but this form is for {$validated['crop']} only.",
                ], 422);
            }
        } else {
            $hasCropPlot = FarmPlot::where('farmer_id', $farmer->id)
                ->whereRaw('LOWER(commodity) = ?', [strtolower($validated['crop'])])
                ->exists();
            if (! $hasCropPlot) {
                return response()->json([
                    'status' => 'error',
                    'message' => "This farmer has no {$validated['crop']} farm plot. Switch crop type or pick another farmer.",
                ], 422);
            }
        }

        if (! empty($validated['id'])) {
            $existing = PestMonitoring::find($validated['id']);
            if ($existing) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Pest inspection already recorded.',
                    'data' => $existing->load('farmer', 'farmPlot'),
                    'duplicate' => true,
                ]);
            }
        }

        $photoPath = null;
        if (! empty($validated['photo_base64'])) {
            $photoPath = $this->storeBase64Image($validated['photo_base64'], 'pest-monitoring');
        }

        $pct = (float) $validated['area_damage_pct'];
        $severity = $pct >= 60 ? 'High' : ($pct >= 30 ? 'Moderate' : 'Low');

        $row = PestMonitoring::create([
            'id' => $validated['id'] ?? null,
            'client_id' => $validated['id'] ?? null,
            'farmer_id' => $validated['farmer_id'],
            'farm_plot_id' => $validated['farm_plot_id'] ?? null,
            'technician_id' => $user->id,
            'crop' => $validated['crop'],
            'variety' => $validated['variety'] ?? null,
            'area_planted' => $validated['area_planted'],
            'days_after_planting' => $validated['days_after_planting'],
            'area_damage_pct' => $pct,
            'farm_location' => $validated['farm_location'] ?? $farmer->permanent_brgy,
            'date_of_inspection' => $validated['date_of_inspection'],
            'pest_name' => $validated['damage_by'],
            'incidence' => (int) round($pct),
            'severity' => $severity,
            'is_outbreak' => $pct >= 30,
            'photo_path' => $photoPath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pest inspection saved.',
            'data' => $row->load('farmer', 'farmPlot'),
        ], 201);
    }
}
