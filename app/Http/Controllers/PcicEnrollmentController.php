<?php

namespace App\Http\Controllers;

use App\Models\FarmPlot;
use App\Models\PcicEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PcicEnrollmentController extends Controller
{
    /**
     * List PCIC enrollments with farmer/plot relations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PcicEnrollment::with([
            'farmer:id,first_name,surname,rsbsa_no,permanent_brgy',
            'farmPlot:id,commodity,size_ha,location_brgy',
            'enrolledBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('coverage_year')) {
            $query->where('coverage_year', (int) $request->query('coverage_year'));
        }

        if ($request->filled('barangay')) {
            $query->whereHas('farmer', fn ($q) => $q->where('permanent_brgy', $request->query('barangay')));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->whereHas('farmer', function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('surname', 'like', $term)
                    ->orWhere('rsbsa_no', 'like', $term);
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'PCIC enrollments retrieved.',
            'data' => $query->orderByDesc('enrolled_at')->paginate(15),
        ]);
    }

    /**
     * Enroll a farmer (and optional plot) in PCIC crop insurance.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farmer_id' => 'required|uuid|exists:farmers,id',
            'farm_plot_id' => 'nullable|uuid|exists:farm_plots,id',
            'crop_season' => 'required|string|max:100',
            'coverage_year' => 'required|integer|min:2020|max:2100',
            'commodity' => 'nullable|string|max:100',
            'insured_area_ha' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $plot = null;
        if (!empty($validated['farm_plot_id'])) {
            $plot = FarmPlot::where('id', $validated['farm_plot_id'])
                ->where('farmer_id', $validated['farmer_id'])
                ->firstOrFail();
        }

        $duplicate = PcicEnrollment::where('farmer_id', $validated['farmer_id'])
            ->where('coverage_year', $validated['coverage_year'])
            ->where('status', 'Active')
            ->when(
                $validated['farm_plot_id'] ?? null,
                fn ($q, $plotId) => $q->where('farm_plot_id', $plotId),
                fn ($q) => $q->whereNull('farm_plot_id')
            )
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'This farmer already has an active PCIC enrollment for this plot and coverage year.',
            ], 409);
        }

        $enrollment = PcicEnrollment::create([
            'farmer_id' => $validated['farmer_id'],
            'farm_plot_id' => $validated['farm_plot_id'] ?? null,
            'crop_season' => $validated['crop_season'],
            'coverage_year' => $validated['coverage_year'],
            'commodity' => $validated['commodity'] ?? $plot?->commodity ?? 'General',
            'insured_area_ha' => $validated['insured_area_ha'] ?? $plot?->size_ha ?? 0,
            'enrolled_by' => $request->user()->id,
            'enrolled_at' => now(),
            'status' => 'Active',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmer enrolled in PCIC crop insurance.',
            'data' => $enrollment->load(['farmer:id,first_name,surname,rsbsa_no', 'farmPlot:id,commodity,size_ha']),
        ], 201);
    }

    /**
     * Update policy reference, status, or remarks.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'policy_reference' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['Active', 'Submitted', 'Withdrawn'])],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $enrollment = PcicEnrollment::findOrFail($id);
        $enrollment->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment updated.',
            'data' => $enrollment->fresh(),
        ]);
    }

    /**
     * Batch export enrollments for PCIC regional submission.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->query('format', 'csv');
        $statusFilter = $request->query('status', 'Active,Submitted');

        $statuses = array_map('trim', explode(',', $statusFilter));

        $enrollments = PcicEnrollment::with(['farmer', 'farmPlot'])
            ->whereIn('status', $statuses)
            ->orderBy('coverage_year')
            ->orderBy('farmer_id')
            ->get();

        $rows = $enrollments->map(function (PcicEnrollment $e) {
            $f = $e->farmer;
            $p = $e->farmPlot;

            return [
                'rsbsa_no' => $f->rsbsa_no,
                'surname' => $f->surname,
                'first_name' => $f->first_name,
                'middle_name' => $f->middle_name,
                'sex' => $f->sex,
                'birthdate' => optional($f->birthdate)->format('Y-m-d'),
                'mobile_number' => $f->mobile_number,
                'barangay' => $f->permanent_brgy,
                'city' => $f->permanent_city,
                'province' => $f->permanent_province,
                'commodity' => $e->commodity,
                'insured_area_ha' => $e->insured_area_ha,
                'plot_location_brgy' => $p?->location_brgy,
                'plot_farm_type' => $p?->farm_type,
                'crop_season' => $e->crop_season,
                'coverage_year' => $e->coverage_year,
                'policy_reference' => $e->policy_reference,
                'status' => $e->status,
                'enrolled_at' => optional($e->enrolled_at)->format('Y-m-d'),
            ];
        });

        if ($format === 'json') {
            return response()->json([
                'status' => 'success',
                'count' => $rows->count(),
                'data' => $rows,
            ]);
        }

        $headers = [
            'RSBSA No', 'Surname', 'First Name', 'Middle Name', 'Sex', 'Birthdate',
            'Mobile', 'Barangay', 'City', 'Province', 'Commodity', 'Insured Area (ha)',
            'Plot Barangay', 'Farm Type', 'Crop Season', 'Coverage Year',
            'Policy Reference', 'Status', 'Enrolled At',
        ];

        $filename = 'pcic-enrollments-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
