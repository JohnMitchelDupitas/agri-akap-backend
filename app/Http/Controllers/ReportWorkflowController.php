<?php

namespace App\Http\Controllers;

use App\Models\ReportWorkflow;
use App\Services\ReportAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class ReportWorkflowController extends Controller
{
    public function __construct(
        private ReportAggregationService $aggregation
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateParams($request);
        $payload = $this->aggregation->aggregate($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Report preview generated.',
            'data' => $payload,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ReportWorkflow::with([
            'collector:id,name',
            'consolidator:id,name',
        ]);

        if ($request->filled('provincial_status')) {
            $query->where('provincial_status', $request->query('provincial_status'));
        }
        if ($request->filled('report_type')) {
            $query->where('report_type', $request->query('report_type'));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report workflows retrieved.',
            'data' => $query->orderByDesc('created_at')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateParams($request);
        $payload = $this->aggregation->aggregate($validated);

        $workflow = ReportWorkflow::create([
            'report_type' => $validated['report_type'],
            'raw_data_collector_id' => $request->user()->id,
            'provincial_status' => 'Pending',
            'report_parameters' => [
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'commodity' => $validated['commodity'] ?? null,
            ],
            'payload_snapshot' => $payload,
        ]);

        $workflow->load(['collector:id,name', 'consolidator:id,name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft report workflow created.',
            'data' => $workflow,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $workflow = ReportWorkflow::with([
            'collector:id,name',
            'consolidator:id,name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Report workflow retrieved.',
            'data' => $workflow,
        ]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $workflow = ReportWorkflow::findOrFail($id);

        if ($workflow->provincial_status !== 'Pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Pending reports can be verified.',
            ], 422);
        }

        $workflow->update([
            'provincial_status' => 'Verified',
            'consolidator_id' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $workflow->load(['collector:id,name', 'consolidator:id,name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Report verified.',
            'data' => $workflow,
        ]);
    }

    public function finalize(Request $request, string $id): JsonResponse
    {
        $workflow = ReportWorkflow::findOrFail($id);

        if ($workflow->provincial_status !== 'Verified') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Verified reports can be finalized.',
            ], 422);
        }

        $payload = $workflow->payload_snapshot;
        if (!$payload) {
            $payload = $this->aggregation->aggregate(array_merge(
                ['report_type' => $workflow->report_type],
                $workflow->report_parameters ?? []
            ));
        }

        $payload['finalized_at'] = now()->toIso8601String();
        $payload['workflow_id'] = $workflow->id;

        $dir = storage_path('app/public/reports');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $filename = $workflow->id . '.json';
        File::put($dir . DIRECTORY_SEPARATOR . $filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $workflow->update([
            'provincial_status' => 'Finalized',
            'consolidator_id' => $workflow->consolidator_id ?? $request->user()->id,
            'submission_date' => now()->toDateString(),
            'finalized_at' => now(),
            'file_url' => 'storage/reports/' . $filename,
            'payload_snapshot' => $payload,
        ]);

        $workflow->load(['collector:id,name', 'consolidator:id,name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Report finalized and snapshot saved.',
            'data' => $workflow,
        ]);
    }

    private function validateParams(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'string', Rule::in(ReportWorkflow::TYPES)],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'barangay' => 'nullable|string|max:100',
            'commodity' => 'nullable|string|max:100',
        ]);
    }
}
