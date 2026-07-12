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
        $user = $request->user();

        $query = DamageAssessment::with([
            'farmer:id,first_name,surname,rsbsa_no,permanent_brgy,mobile_number',
            'farmPlot:id,commodity,size_ha,location_brgy',
            'technician:id,name',
            'verifier:id,name',
            'approver:id,name',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Role-scoped default views
        if ($user->role === 'technician') {
            $query->where('technician_id', $user->id);
        } elseif ($user->role === 'barangay_official' && !$request->filled('status')) {
            // Default queue for barangay officials is what needs pre-assessment.
            $query->whereIn('status', ['Pending', 'Verified']);
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
            'farmer', 'farmPlot', 'technician:id,name', 'verifier:id,name', 'approver:id,name',
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
            'id' => 'nullable|uuid', // client-generated UUID for offline sync idempotency
            'farm_plot_id' => 'required|exists:farm_plots,id',
            'farmer_id' => 'nullable|exists:farmers,id',
            'calamity_name' => 'required|string|max:255',
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
            'calamity_name' => $validated['calamity_name'],
            'date_of_calamity' => $validated['date_of_calamity'],
            'damage_percentage' => $validated['damage_percentage'],
            'estimated_value_lost' => $validated['estimated_value_lost'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'photo_evidence_path' => $path,
            'status' => 'Pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Damage assessment filed. Awaiting Barangay pre-assessment.',
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
}
