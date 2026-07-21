<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\FarmPlot;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    use DecodesBase64Image;

    public function __construct(private DistributionController $distributions)
    {
    }

    /**
     * Bulk upload of records queued offline on a technician's device.
     * Each item carries a client-generated UUID so sync is idempotent:
     * re-uploading an already-stored record resolves as a "duplicate"
     * rather than creating a conflict.
     *
     * Expected payload:
     * {
     *   "device_id": "...",
     *   "distributions": [ { "client_id","farmer_id","program_id","claimed_at" }, ... ],
     *   "assessments":   [ { "id","farm_plot_id","calamity_name",... "photo_base64" }, ... ]
     * }
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id');
        $technicianId = $request->user()->id;

        $results = [
            'distributions' => [],
            'assessments' => [],
        ];

        foreach ((array) $request->input('distributions', []) as $item) {
            $results['distributions'][] = $this->syncDistribution($item, $technicianId, $deviceId);
        }

        foreach ((array) $request->input('assessments', []) as $item) {
            $results['assessments'][] = $this->syncAssessment($item, $technicianId, $deviceId);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sync batch processed.',
            'results' => $results,
        ], 200);
    }

    private function syncDistribution(array $item, string $technicianId, ?string $deviceId): array
    {
        $clientId = $item['client_id'] ?? ($item['id'] ?? null);

        $validator = Validator::make($item, [
            'farmer_id' => 'required|uuid|exists:farmers,id',
            'program_id' => 'required|uuid|exists:programs,id',
        ]);

        if ($validator->fails()) {
            return $this->itemResult($clientId, 'failed', $validator->errors()->first());
        }

        $payload = [
            'client_id' => $clientId,
            'farmer_id' => $item['farmer_id'],
            'program_id' => $item['program_id'],
            'device_id' => $item['device_id'] ?? $deviceId,
            'claimed_at' => $item['claimed_at'] ?? null,
            'geo_tag_lat' => $item['geo_tag_lat'] ?? null,
            'geo_tag_long' => $item['geo_tag_long'] ?? null,
            'photo_proof_base64' => $item['photo_proof_base64'] ?? null,
        ];

        $result = $this->distributions->executeClaim($payload, $technicianId);

        return $this->itemResult($clientId, $result['outcome'], $result['body']['message'] ?? '');
    }

    private function syncAssessment(array $item, string $technicianId, ?string $deviceId): array
    {
        $clientId = $item['id'] ?? ($item['client_id'] ?? null);

        $validator = Validator::make($item, [
            'farm_plot_id' => 'required|uuid|exists:farm_plots,id',
            'calamity_type' => ['required', Rule::in(['Typhoon', 'Flood', 'Drought', 'Pest Outbreak', 'Hail', 'Other'])],
            'calamity_name' => 'nullable|string|max:255',
            'crop_stage' => ['nullable', Rule::in(['Seedling', 'Vegetative', 'Reproductive', 'Maturity', 'Harvested'])],
            'area_destroyed_ha' => 'nullable|numeric|min:0',
            'date_of_calamity' => 'required|date',
            'damage_percentage' => 'required|numeric|min:0|max:100',
            'photo_base64' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->itemResult($clientId, 'failed', $validator->errors()->first());
        }

        // Idempotency: skip already-synced client UUIDs.
        if ($clientId && DamageAssessment::whereKey($clientId)->exists()) {
            return $this->itemResult($clientId, 'duplicate', 'Assessment already synced.');
        }

        try {
            $path = $this->storeBase64Image($item['photo_base64'], 'assessments');
            if ($path === null) {
                return $this->itemResult($clientId, 'failed', 'Photo evidence could not be decoded.');
            }

            $farmerId = $item['farmer_id'] ?? FarmPlot::whereKey($item['farm_plot_id'])->value('farmer_id');

            $assessment = DamageAssessment::create([
                'id' => $clientId,
                'farm_plot_id' => $item['farm_plot_id'],
                'farmer_id' => $farmerId,
                'technician_id' => $technicianId,
                'calamity_type' => $item['calamity_type'],
                'calamity_name' => $item['calamity_name'] ?? $item['calamity_type'],
                'crop_stage' => $item['crop_stage'] ?? null,
                'area_destroyed_ha' => $item['area_destroyed_ha'] ?? null,
                'date_of_calamity' => $item['date_of_calamity'],
                'damage_percentage' => $item['damage_percentage'],
                'estimated_value_lost' => $item['estimated_value_lost'] ?? null,
                'latitude' => $item['latitude'] ?? null,
                'longitude' => $item['longitude'] ?? null,
                'device_id' => $item['device_id'] ?? $deviceId,
                'photo_evidence_path' => $path,
                'status' => 'Pending',
            ]);

            return $this->itemResult($clientId ?? $assessment->id, 'synced', 'Assessment filed.');
        } catch (\Exception $e) {
            Log::error('Assessment sync failed: ' . $e->getMessage());
            return $this->itemResult($clientId, 'failed', 'Server error while saving assessment.');
        }
    }

    private function itemResult(?string $clientId, string $outcome, string $message): array
    {
        return [
            'client_id' => $clientId,
            'outcome' => $outcome, // synced | duplicate | failed
            'message' => $message,
        ];
    }
}
