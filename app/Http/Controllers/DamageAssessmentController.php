<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\FarmPlot;
use App\Models\PcicEnrollment;
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
        $user = $request->user();

        $query = DamageAssessment::with([
            'farmer:id,first_name,surname,rsbsa_no,permanent_brgy,mobile_number',
            'farmPlot:id,commodity,size_ha,location_brgy',
            'technician:id,name',
            'verifier:id,name',
            'approver:id,name',
            'noticeFiler:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('calamity_type')) {
            $query->where('calamity_type', $request->query('calamity_type'));
        }

        if ($request->query('priority') === 'unfiled') {
            $query->where('status', 'Approved')->where('is_pcic_notice_filed', false);
        }

        // Role-scoped default views
        if ($user->role === 'technician') {
            $query->where('technician_id', $user->id);
        } elseif ($user->role === 'barangay_official' && !$request->filled('status')) {
            $query->whereIn('status', ['Pending', 'Verified']);
        }

        $sortField = $request->query('sort', 'date_of_calamity');
        $sortDir = $request->query('dir', 'desc');
        if (in_array($sortField, ['date_of_calamity', 'created_at', 'damage_percentage'], true)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('date_of_calamity');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Damage assessments retrieved.',
            'data' => $query->paginate(15),
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
        $validated = $request->validate([
            'id' => 'nullable|uuid',
            'farm_plot_id' => 'required|exists:farm_plots,id',
            'farmer_id' => 'nullable|exists:farmers,id',
            'calamity_type' => ['required', Rule::in(['Typhoon', 'Flood', 'Drought', 'Pest Outbreak', 'Hail', 'Other'])],
            'calamity_name' => 'nullable|string|max:255',
            'crop_stage' => ['nullable', Rule::in(['Seedling', 'Vegetative', 'Reproductive', 'Maturity', 'Harvested'])],
            'area_destroyed_ha' => 'nullable|numeric|min:0',
            'date_of_calamity' => 'required|date',
            'damage_percentage' => 'required|numeric|min:0|max:100',
            'estimated_value_lost' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'device_id' => 'nullable|string|max:255',
            'photo_base64' => 'required|string',
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

        $path = $this->storeBase64Image($request->input('photo_base64'), 'assessments');
        if ($path === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'The photo evidence could not be decoded. Please recapture.',
            ], 422);
        }

        $assessment = DamageAssessment::create([
            'id' => $validated['id'] ?? null,
            'farm_plot_id' => $validated['farm_plot_id'],
            'farmer_id' => $validated['farmer_id'],
            'technician_id' => $request->user()->id,
            'calamity_type' => $validated['calamity_type'],
            'calamity_name' => $validated['calamity_name'] ?? $validated['calamity_type'],
            'crop_stage' => $validated['crop_stage'] ?? null,
            'area_destroyed_ha' => $validated['area_destroyed_ha'] ?? null,
            'date_of_calamity' => $validated['date_of_calamity'],
            'damage_percentage' => $validated['damage_percentage'],
            'estimated_value_lost' => $validated['estimated_value_lost'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'photo_evidence_path' => $path,
            'status' => 'Pending',
            'is_pcic_notice_filed' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notice of claim filed. Awaiting Barangay pre-assessment.',
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

    /**
     * MAO Admin files the PCIC Notice of Claim for an approved assessment.
     */
    public function fileNotice(Request $request, string $id): JsonResponse
    {
        $assessment = DamageAssessment::findOrFail($id);

        if ($assessment->status !== 'Approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Approved assessments can be filed with PCIC.',
            ], 409);
        }

        if ($assessment->is_pcic_notice_filed) {
            return response()->json([
                'status' => 'error',
                'message' => 'PCIC notice has already been filed for this assessment.',
            ], 409);
        }

        $assessment->update([
            'status' => 'Claimed',
            'is_pcic_notice_filed' => true,
            'pcic_notice_filed_at' => now(),
            'pcic_notice_filed_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'PCIC Notice of Claim filed successfully.',
            'data' => $assessment->fresh()->load('noticeFiler:id,name'),
        ], 200);
    }

    /**
     * Print-ready payload for the PCIC Notice of Claim document.
     */
    public function noticeData(string $id): JsonResponse
    {
        $assessment = DamageAssessment::with([
            'farmer',
            'farmPlot',
            'technician:id,name',
            'verifier:id,name',
            'approver:id,name',
            'noticeFiler:id,name',
        ])->findOrFail($id);

        if (!in_array($assessment->status, ['Approved', 'Claimed'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notice data is only available for Approved or Claimed assessments.',
            ], 409);
        }

        $enrollment = PcicEnrollment::where('farmer_id', $assessment->farmer_id)
            ->when($assessment->farm_plot_id, fn ($q) => $q->where('farm_plot_id', $assessment->farm_plot_id))
            ->whereIn('status', ['Active', 'Submitted'])
            ->orderByDesc('enrolled_at')
            ->first();

        $farmer = $assessment->farmer;
        $plot = $assessment->farmPlot;

        return response()->json([
            'status' => 'success',
            'data' => [
                'assessment_id' => $assessment->id,
                'status' => $assessment->status,
                'is_pcic_notice_filed' => $assessment->is_pcic_notice_filed,
                'pcic_notice_filed_at' => $assessment->pcic_notice_filed_at,
                'farmer' => [
                    'name' => trim(($farmer->first_name ?? '') . ' ' . ($farmer->surname ?? '')),
                    'rsbsa_no' => $farmer->rsbsa_no,
                    'mobile_number' => $farmer->mobile_number,
                    'barangay' => $farmer->permanent_brgy,
                    'city' => $farmer->permanent_city,
                    'province' => $farmer->permanent_province,
                    'sex' => $farmer->sex,
                    'birthdate' => optional($farmer->birthdate)->format('Y-m-d'),
                ],
                'plot' => [
                    'commodity' => $plot->commodity ?? null,
                    'size_ha' => $plot->size_ha ?? null,
                    'location_brgy' => $plot->location_brgy ?? null,
                    'farm_type' => $plot->farm_type ?? null,
                ],
                'calamity' => [
                    'type' => $assessment->calamity_type,
                    'name' => $assessment->calamity_name,
                    'date' => optional($assessment->date_of_calamity)->format('Y-m-d'),
                    'crop_stage' => $assessment->crop_stage,
                    'damage_percentage' => $assessment->damage_percentage,
                    'area_destroyed_ha' => $assessment->area_destroyed_ha,
                    'estimated_value_lost' => $assessment->estimated_value_lost,
                ],
                'geo' => [
                    'latitude' => $assessment->latitude,
                    'longitude' => $assessment->longitude,
                ],
                'photo_url' => $assessment->photo_url,
                'audit' => [
                    'technician' => $assessment->technician?->name,
                    'verifier' => $assessment->verifier?->name,
                    'approver' => $assessment->approver?->name,
                    'notice_filer' => $assessment->noticeFiler?->name,
                    'verified_at' => $assessment->verified_at,
                    'approved_at' => $assessment->approved_at,
                ],
                'enrollment' => $enrollment ? [
                    'policy_reference' => $enrollment->policy_reference,
                    'coverage_year' => $enrollment->coverage_year,
                    'crop_season' => $enrollment->crop_season,
                    'insured_area_ha' => $enrollment->insured_area_ha,
                ] : null,
            ],
        ], 200);
    }
}
