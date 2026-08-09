<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\FarmPlot;
use App\Models\Farmer;
use App\Models\PestMonitoring;
use App\Models\PlantingLog;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    use DecodesBase64Image;

    public function __construct(private DistributionController $distributions)
    {
    }

    /**
     * Alias matching the offline sync engine naming.
     */
    public function bulkSync(Request $request): JsonResponse
    {
        return $this->bulkUpload($request);
    }

    /**
     * Bulk upload of records queued offline on a technician's device.
     *
     * Expected payload (all keys optional):
     * {
     *   "device_id": "...",
     *   "distributions": [...],
     *   "assessments": [...],
     *   "planting_logs": [...],
     *   "pest_reports": [...],
     *   "farm_profiles": [...]
     * }
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id');
        $technicianId = $request->user()->id;

        $results = [
            'distributions' => [],
            'assessments' => [],
            'planting_logs' => [],
            'pest_reports' => [],
            'farm_profiles' => [],
        ];

        foreach ((array) $request->input('distributions', []) as $item) {
            $results['distributions'][] = $this->syncDistribution($item, $technicianId, $deviceId);
        }

        foreach ((array) $request->input('assessments', []) as $item) {
            $results['assessments'][] = $this->syncAssessment($item, $technicianId, $deviceId);
        }

        $hasOfflineBatch = $request->has('planting_logs')
            || $request->has('pest_reports')
            || $request->has('farm_profiles');

        if ($hasOfflineBatch) {
            try {
                DB::beginTransaction();

                if ($request->has('planting_logs')) {
                    foreach ((array) $request->input('planting_logs', []) as $item) {
                        $results['planting_logs'][] = $this->syncPlantingLog($item, $technicianId, $deviceId);
                    }
                }

                if ($request->has('pest_reports')) {
                    foreach ((array) $request->input('pest_reports', []) as $item) {
                        $results['pest_reports'][] = $this->syncPestReport($item, $technicianId, $deviceId);
                    }
                }

                if ($request->has('farm_profiles')) {
                    foreach ((array) $request->input('farm_profiles', []) as $item) {
                        $results['farm_profiles'][] = $this->syncFarmProfile($item, $deviceId);
                    }
                }

                // Fail the batch if any offline item failed validation/insert.
                $offlineFailed = collect([
                    ...$results['planting_logs'],
                    ...$results['pest_reports'],
                    ...$results['farm_profiles'],
                ])->contains(fn ($r) => ($r['outcome'] ?? '') === 'failed');

                if ($offlineFailed) {
                    DB::rollBack();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sync failed. Offline batch rolled back.',
                        'results' => $results,
                    ], 500);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Offline bulk sync failed: '.$e->getMessage());

                return response()->json([
                    'status' => 'error',
                    'message' => 'Sync failed. '.$e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sync Successful',
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
            Log::error('Assessment sync failed: '.$e->getMessage());

            return $this->itemResult($clientId, 'failed', 'Server error while saving assessment.');
        }
    }

    private function syncPlantingLog(array $item, string $technicianId, ?string $deviceId): array
    {
        $clientId = $item['client_id'] ?? ($item['id'] ?? null);

        $farmerId = $this->resolveFarmerId($item['farmer_id'] ?? null, $item['rsbsa_no'] ?? null);

        $validator = Validator::make([
            ...$item,
            'farmer_id' => $farmerId,
        ], [
            'farmer_id' => 'required|uuid|exists:farmers,id',
            'crop_type' => 'required|string|max:64',
            'variety' => 'required|string|max:128',
            'area_planted' => 'required|numeric|min:0',
            'date_planted' => 'required|date',
            'status' => 'nullable|string|max:64',
            'water_source' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->itemResult($clientId, 'failed', $validator->errors()->first());
        }

        if ($clientId && PlantingLog::where('client_id', $clientId)->exists()) {
            return $this->itemResult($clientId, 'duplicate', 'Planting log already synced.');
        }

        $log = PlantingLog::create([
            'client_id' => $clientId,
            'farmer_id' => $farmerId,
            'technician_id' => $technicianId,
            'crop_type' => $item['crop_type'],
            'variety' => $item['variety'],
            'area_planted' => $item['area_planted'],
            'date_planted' => $item['date_planted'],
            'status' => $item['status'] ?? 'Active',
            'water_source' => $item['water_source'] ?? null,
            'latitude' => $item['latitude'] ?? null,
            'longitude' => $item['longitude'] ?? null,
            'device_id' => $item['device_id'] ?? $deviceId,
        ]);

        return $this->itemResult($clientId ?? $log->id, 'synced', 'Planting log saved.');
    }

    private function syncPestReport(array $item, string $technicianId, ?string $deviceId): array
    {
        $clientId = $item['client_id'] ?? ($item['id'] ?? null);
        $farmerId = $this->resolveFarmerId($item['farmer_id'] ?? null, $item['rsbsa_no'] ?? null);

        $validator = Validator::make($item, [
            'crop' => 'nullable|string|max:64',
            'incidence' => 'required|numeric|min:0|max:100',
            'severity' => 'required|string|max:32',
            'advisory' => 'nullable|string',
            'is_outbreak' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->itemResult($clientId, 'failed', $validator->errors()->first());
        }

        if ($clientId && PestMonitoring::where('client_id', $clientId)->exists()) {
            return $this->itemResult($clientId, 'duplicate', 'Pest report already synced.');
        }

        $photoPath = null;
        if (! empty($item['photo_base64'])) {
            $photoPath = $this->storeBase64Image($item['photo_base64'], 'pest-monitoring');
            if ($photoPath === null) {
                return $this->itemResult($clientId, 'failed', 'Pest photo could not be decoded.');
            }
        }

        $row = PestMonitoring::create([
            'client_id' => $clientId,
            'farmer_id' => $farmerId,
            'technician_id' => $technicianId,
            'crop' => $item['crop'] ?? null,
            'pest_name' => $item['pest_name'] ?? null,
            'incidence' => (int) $item['incidence'],
            'severity' => $item['severity'],
            'advisory' => $item['advisory'] ?? null,
            'is_outbreak' => (bool) ($item['is_outbreak'] ?? false),
            'photo_path' => $photoPath,
            'latitude' => $item['lat'] ?? ($item['latitude'] ?? null),
            'longitude' => $item['lng'] ?? ($item['longitude'] ?? null),
            'report_ref' => $item['report_id'] ?? null,
            'item_distributed' => $item['item_distributed'] ?? null,
            'quantity' => isset($item['quantity']) ? (string) $item['quantity'] : null,
            'device_id' => $item['device_id'] ?? $deviceId,
        ]);

        return $this->itemResult($clientId ?? $row->id, 'synced', 'Pest report saved.');
    }

    private function syncFarmProfile(array $item, ?string $deviceId): array
    {
        $clientId = $item['client_id'] ?? ($item['id'] ?? null);
        $farmerId = $this->resolveFarmerId($item['farmer_id'] ?? null, $item['rsbsa_no'] ?? null);

        if (! $farmerId) {
            return $this->itemResult($clientId, 'failed', 'farmer_id is required to update farm plots.');
        }

        $coords = $this->parseCoordinates($item['coordinates'] ?? null);
        if ($coords === null) {
            return $this->itemResult($clientId, 'failed', 'Invalid farm coordinates payload.');
        }

        $plot = FarmPlot::where('farmer_id', $farmerId)->orderBy('created_at')->first();
        if (! $plot) {
            return $this->itemResult($clientId, 'failed', 'No farm plot found for this farmer.');
        }

        $lat = $coords['lat'];
        $lng = $coords['lng'];
        $totalArea = isset($item['total_area']) ? (float) $item['total_area'] : null;

        $plot->latitude = $lat;
        $plot->longitude = $lng;
        if ($totalArea !== null && $totalArea > 0) {
            $plot->size_ha = $totalArea;
            $plot->total_parcel_area_ha = $totalArea;
        }
        $plot->save();

        DB::update(
            'UPDATE farm_plots SET coordinates = POINT(?, ?) WHERE id = ?',
            [$lng, $lat, $plot->id]
        );

        return $this->itemResult($clientId, 'synced', 'Farm plot profile updated.');
    }

    /**
     * Accept UUID farmer_id or resolve via RSBSA number.
     */
    private function resolveFarmerId(?string $farmerId, ?string $rsbsaNo = null): ?string
    {
        if ($farmerId && Str::isUuid($farmerId) && Farmer::whereKey($farmerId)->exists()) {
            return $farmerId;
        }

        $lookup = $rsbsaNo ?: $farmerId;
        if ($lookup) {
            return Farmer::where('rsbsa_no', $lookup)->value('id');
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function parseCoordinates(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        if (! is_array($raw)) {
            return null;
        }

        // Single pin { lat, lng }
        if (isset($raw['lat'], $raw['lng'])) {
            return ['lat' => (float) $raw['lat'], 'lng' => (float) $raw['lng']];
        }

        // Polygon / walk points — use first vertex as pin
        if (isset($raw[0]['lat'], $raw[0]['lng'])) {
            return ['lat' => (float) $raw[0]['lat'], 'lng' => (float) $raw[0]['lng']];
        }

        return null;
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
