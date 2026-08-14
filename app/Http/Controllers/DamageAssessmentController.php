<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\FarmPlot;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DamageAssessmentController extends Controller
{
    use DecodesBase64Image;

    /**
     * List assessments for the review queues.
     * Barangay Officials only see 'Pending' items awaiting pre-assessment;
     * technicians see the reports they filed; MAO admins see everything.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'calamity_type' => ['nullable', 'string'],
            'barangay' => ['nullable', 'string'],
            'commodity' => ['nullable', 'string'],
            'severity' => ['nullable', Rule::in(['Low', 'Moderate', 'Severe'])],
            'sort' => ['nullable', Rule::in(['date_of_calamity', 'created_at', 'damage_percentage'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'dispatch_queue' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $query = DamageAssessment::with([
            'farmer:id,first_name,surname,middle_name,ext_name,rsbsa_no,permanent_brgy,mobile_number',
            'farmPlot:id,commodity,size_ha,location_brgy',
            'technician:id,name',
            'verifier:id,name',
            'approver:id,name',
            'noticeFiler:id,name',
        ]);

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['calamity_type'])) {
            $query->where('calamity_type', $validated['calamity_type']);
        }

        if (!empty($validated['barangay'])) {
            $brgy = $validated['barangay'];
            $query->where(function ($q) use ($brgy) {
                $q->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $brgy))
                    ->orWhereHas('farmPlot', fn ($fp) => $fp->where('location_brgy', $brgy));
            });
        }

        if (!empty($validated['commodity'])) {
            $query->whereHas('farmPlot', fn ($fp) => $fp->where('commodity', $validated['commodity']));
        }

        if (!empty($validated['severity'])) {
            $severity = strtolower((string) $validated['severity']);
            if ($severity === 'low') {
                $query->where('damage_percentage', '<', 30);
            } elseif ($severity === 'moderate') {
                $query->whereBetween('damage_percentage', [30, 60]);
            } elseif ($severity === 'severe') {
                $query->where('damage_percentage', '>', 60);
            }
        }

        // Role-scoped default views
        if ($user->role === 'technician') {
            if ($request->boolean('dispatch_queue')) {
                $query->where(function ($q) {
                    $q->whereNull('photo_evidence_path')->orWhereNull('latitude');
                });
            } else {
                $query->where('technician_id', $user->id);
            }
        } elseif ($user->role === 'barangay_official' && empty($validated['status'])) {
            $query->whereIn('status', ['Pending', 'Verified']);
        }

        $sortField = $validated['sort'] ?? 'date_of_calamity';
        $sortDir = $validated['dir'] ?? 'desc';
        if (in_array($sortField, ['date_of_calamity', 'created_at', 'damage_percentage'], true)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('date_of_calamity');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Damage assessments retrieved.',
            'data' => $query->paginate((int) ($validated['per_page'] ?? 50)),
        ], 200);
    }

    /**
     * Show a single assessment with full relations.
     */
    public function show(string $id): JsonResponse
    {
        $assessment = DamageAssessment::with([
            'farmer', 'farmPlot', 'technician:id,name', 'verifier:id,name',
            'approver:id,name', 'noticeFiler:id,name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $assessment,
        ], 200);
    }

    /**
     * File a new geotagged damage assessment (technician in the field).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isBarangayEncoder = $user->role === 'barangay_official';

        $validated = $request->validate([
            'id' => 'nullable|uuid',
            'farm_plot_id' => 'required|exists:farm_plots,id',
            'farmer_id' => 'nullable|exists:farmers,id',
            'calamity_type' => ['required', Rule::in(['Typhoon', 'Flood', 'Drought', 'Pest Outbreak', 'Hail', 'Other'])],
            'calamity_name' => 'nullable|string|max:255',
            'crop_stage' => ['nullable', Rule::in(['Seedling', 'Vegetative', 'Reproductive', 'Maturity', 'Harvested'])],
            'area_destroyed_ha' => 'nullable|numeric|min:0',
            'area_planted_ha' => 'nullable|numeric|min:0',
            'date_of_calamity' => 'required|date',
            'damage_percentage' => 'required|numeric|min:0|max:100',
            'estimated_value_lost' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'device_id' => 'nullable|string|max:255',
            // Field technicians must attach geotagged photo evidence; barangay ledger encoding may omit it.
            'photo_base64' => ($isBarangayEncoder ? 'nullable' : 'required').'|string',
        ]);

        // Idempotency: if this client UUID was already synced, return it.
        if (!empty($validated['id'])) {
            $existing = DamageAssessment::find($validated['id']);
            if ($existing) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Assessment already recorded.',
                    'data' => $existing,
                    'duplicate' => true,
                ], 200);
            }
        }

        // Derive farmer from the plot when the client did not send it.
        if (empty($validated['farmer_id'])) {
            $validated['farmer_id'] = FarmPlot::whereKey($validated['farm_plot_id'])->value('farmer_id');
        }

        if ($isBarangayEncoder && $user->assigned_barangay) {
            $farmerBrgy = optional(
                \App\Models\Farmer::find($validated['farmer_id'])
            )->permanent_brgy;
            if ($farmerBrgy && $farmerBrgy !== $user->assigned_barangay) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only encode farmers from your assigned barangay.',
                ], 403);
            }
        }

        $path = null;
        if (!empty($validated['photo_base64'])) {
            $path = $this->storeBase64Image($validated['photo_base64'], 'assessments');
            if ($path === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The photo evidence could not be decoded. Please recapture.',
                ], 422);
            }
        } elseif (! $isBarangayEncoder) {
            return response()->json([
                'status' => 'error',
                'message' => 'The photo evidence could not be decoded. Please recapture.',
            ], 422);
        }

        $assessment = DamageAssessment::create([
            'id' => $validated['id'] ?? null,
            'farm_plot_id' => $validated['farm_plot_id'],
            'farmer_id' => $validated['farmer_id'],
            'technician_id' => $user->id,
            'calamity_type' => $validated['calamity_type'],
            'calamity_name' => $validated['calamity_name'] ?? $validated['calamity_type'],
            'crop_stage' => $validated['crop_stage'] ?? null,
            'area_destroyed_ha' => $validated['area_destroyed_ha'] ?? null,
            'area_planted_ha' => $validated['area_planted_ha'] ?? null,
            'date_of_calamity' => $validated['date_of_calamity'],
            'damage_percentage' => $validated['damage_percentage'],
            'estimated_value_lost' => $validated['estimated_value_lost'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'photo_evidence_path' => $path,
            // Barangay-encoded ledger entries are already pre-validated at source.
            'status' => $isBarangayEncoder ? 'Verified' : 'Pending',
            'verified_by' => $isBarangayEncoder ? $user->id : null,
            'verified_at' => $isBarangayEncoder ? now() : null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $isBarangayEncoder
                ? 'Calamity assessment encoded and saved for MAO reporting.'
                : 'Damage report recorded. Awaiting Barangay pre-assessment.',
            'data' => $assessment->load('farmer:id,first_name,surname'),
        ], 201);
    }

    /**
     * Barangay Official pre-assessment: mark a Pending report as Verified.
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $assessment = DamageAssessment::findOrFail($id);

        if ($assessment->status !== 'Pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Pending assessments can be pre-assessed.',
            ], 409);
        }

        $assessment->update([
            'status' => 'Verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'remarks' => $validated['remarks'] ?? $assessment->remarks,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Assessment pre-assessed and endorsed to MAO for approval.',
            'data' => $assessment->fresh(),
        ], 200);
    }

    /**
     * Technician on-site validation: attach GPS + photo evidence to a barangay report.
     */
    public function fieldValidate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo_base64' => 'required|string',
            'area_destroyed_ha' => 'nullable|numeric|min:0',
            'damage_percentage' => 'nullable|numeric|min:0|max:100',
            'crop_stage' => ['nullable', Rule::in(['Seedling', 'Vegetative', 'Reproductive', 'Maturity', 'Harvested'])],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $assessment = DamageAssessment::findOrFail($id);
        $path = $this->storeBase64Image($validated['photo_base64'], 'assessments');
        if ($path === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'The photo evidence could not be decoded. Please recapture.',
            ], 422);
        }

        $assessment->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'photo_evidence_path' => $path,
            'technician_id' => $request->user()->id,
            'area_destroyed_ha' => $validated['area_destroyed_ha'] ?? $assessment->area_destroyed_ha,
            'damage_percentage' => $validated['damage_percentage'] ?? $assessment->damage_percentage,
            'crop_stage' => $validated['crop_stage'] ?? $assessment->crop_stage,
            'remarks' => $validated['remarks'] ?? $assessment->remarks,
            'status' => $assessment->status === 'Pending' ? 'Verified' : $assessment->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Field validation saved. Assessment added to the MAO rehabilitation masterlist.',
            'data' => $assessment->fresh()->load('farmer', 'farmPlot'),
        ]);
    }

    /**
     * MAO Admin final decision: Approved or Rejected.
     */
    public function decide(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['Approved', 'Rejected'])],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $assessment = DamageAssessment::findOrFail($id);

        if (!in_array($assessment->status, ['Verified', 'Pending'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This assessment has already been finalized.',
            ], 409);
        }

        $assessment->update([
            'status' => $validated['decision'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'remarks' => $validated['remarks'] ?? $assessment->remarks,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Assessment marked as {$validated['decision']}.",
            'data' => $assessment->fresh(),
        ], 200);
    }
}
