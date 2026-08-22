<?php

namespace App\Http\Controllers;

use App\Models\FarmPlot;
use App\Models\Farmer;
use App\Services\FarmAreaBudgetService;
use App\Services\PolygonIntegrityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FarmPlotController extends Controller
{
    /** Collision radius in meters — blocks double-claim of the same parcel (centroid-only path). */
    private const COLLISION_RADIUS_METERS = 15;

    public function __construct(
        private readonly PolygonIntegrityService $polygonIntegrity,
        private readonly FarmAreaBudgetService $farmAreaBudget,
    ) {}


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

    /**
     * Register a geotagged farm plot from the technician mobile app.
     *
     * Accepts an optional `coordinates` polygon array `[{lat, lng}, ...]`. When
     * supplied the endpoint runs two DA-RSBSA polygon integrity checks before
     * persisting, and stores the boundary for future overlap detection:
     *
     *   1. Start/End Gap Rule  — first and last vertices must be ≤ 10 m apart.
     *   2. Spatial Overlap Rule — new boundary must not intersect any existing plot.
     *
     * When `coordinates` is omitted the legacy centroid-only 15 m collision guard
     * still applies (backward compatible).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farmer_id'                   => 'required|uuid|exists:farmers,id',
            'latitude'                    => 'required|numeric|between:-90,90',
            'longitude'                   => 'required|numeric|between:-180,180',
            'location_brgy'               => 'required|string|max:100',
            'location_city'               => 'nullable|string|max:100',
            'location_province'           => 'nullable|string|max:100',
            'ownership_type'              => 'required|in:Owner,Tenant,Lessee,Registered Owner',
            'landowner_name'              => 'nullable|string|max:255',
            'size_ha'                     => 'required|numeric|min:0.0001|max:9999',
            'commodity'                   => 'required|string|max:100',
            'farm_type'                   => 'nullable|string|max:100',
            'proof_of_ownership_document' => 'nullable|string|max:100',
            'remarks'                     => 'nullable|string|max:1000',
            // Optional boundary polygon — enables full spatial integrity checks.
            'coordinates'                 => 'nullable|array|min:3',
            'coordinates.*.lat'           => 'required_with:coordinates|numeric|between:-90,90',
            'coordinates.*.lng'           => 'required_with:coordinates|numeric|between:-180,180',
        ]);

        $lat    = (float) $validated['latitude'];
        $lng    = (float) $validated['longitude'];
        $points = null;

        // ── Polygon integrity checks (when full boundary is supplied) ──────────
        if (! empty($validated['coordinates'])) {
            $points = $this->polygonIntegrity->normalisePoints($validated['coordinates']);

            if ($points === null) {
                return response()->json([
                    'error'   => 'Invalid Coordinates',
                    'message' => 'Validation Failed: The coordinates array could not be parsed into a valid polygon.',
                ], 422);
            }

            // 1. DA Start/End Gap Rule
            if ($this->polygonIntegrity->hasUnclosedGap($points)) {
                $first = $points[0];
                $last  = $points[count($points) - 1];
                $gapM  = round($this->polygonIntegrity->haversineMeters(
                    $first['lat'], $first['lng'], $last['lat'], $last['lng'],
                ));

                return response()->json([
                    'error'   => 'Unclosed Polygon',
                    'message' => "Validation Failed: The start and end points of the perimeter walk are {$gapM} m apart. "
                        . 'DA guidelines require ≤ ' . PolygonIntegrityService::GAP_LIMIT_METERS . ' m. '
                        . 'Please walk back to the starting stake before completing the boundary.',
                ], 422);
            }

            // 2. DA Spatial Overlap Rule
            $collision = $this->polygonIntegrity->findOverlappingPlot($points);
            if ($collision !== null) {
                return response()->json([
                    'error'   => 'Polygon Overlap',
                    'message' => 'Validation Failed: Polygon overlaps with an existing farm boundary. Please adjust coordinates.',
                ], 422);
            }
        } else {
            // Legacy centroid-only guard for backward compatibility.
            if ($this->hasCoordinateCollision($lat, $lng)) {
                return response()->json([
                    'error'   => 'Coordinate Collision',
                    'message' => 'This land parcel is already registered to another user. Please verify tenancy/ownership.',
                ], 409);
            }
        }

        $ownership = $validated['ownership_type'] === 'Owner'
            ? 'Registered Owner'
            : $validated['ownership_type'];

        $landownerName = $validated['landowner_name'] ?? null;
        $size = (float) $validated['size_ha'];

        $farmer = Farmer::findOrFail($validated['farmer_id']);
        $budgetError = $this->farmAreaBudget->assertWithinBudget($farmer, $size);
        if ($budgetError) {
            return $budgetError;
        }

        $plot = DB::transaction(function () use ($validated, $lat, $lng, $ownership, $landownerName, $points, $size) {
            $id   = (string) Str::uuid();

            DB::table('farm_plots')->insert([
                'id'                          => $id,
                'farmer_id'                   => $validated['farmer_id'],
                'location_brgy'               => $validated['location_brgy'],
                'location_city'               => $validated['location_city'] ?? 'Echague',
                'location_province'           => $validated['location_province'] ?? 'Isabela',
                'latitude'                    => $lat,
                'longitude'                   => $lng,
                'total_parcel_area_ha'        => $size,
                'is_ancestral_domain'         => false,
                'is_agrarian_reform_beneficiary' => false,
                'ownership_type'              => $ownership,
                'landowner_name'              => $landownerName,
                'proof_of_ownership_document' => $validated['proof_of_ownership_document'] ?? 'Geotag Field Capture',
                'commodity'                   => $validated['commodity'],
                'size_ha'                     => $size,
                'farm_type'                   => $validated['farm_type'] ?? 'Irrigated',
                'is_organic'                  => false,
                'remarks'                     => $validated['remarks'] ?? null,
                // Persist boundary polygon for future overlap detection.
                'boundary_points'             => $points !== null ? json_encode($points) : null,
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);

            DB::update(
                'UPDATE farm_plots SET coordinates = POINT(?, ?) WHERE id = ?',
                [$lng, $lat, $id],
            );

            return FarmPlot::with('farmer:id,first_name,surname,rsbsa_no,permanent_brgy')->findOrFail($id);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Farm plot geotagged successfully.',
            'data'    => $plot,
        ], 201);
    }

    /**
     * Soft-delete a farm plot (admin cleanup of legacy duplicate inserts).
     */
    public function destroy(string $id): JsonResponse
    {
        $plot = FarmPlot::findOrFail($id);
        $plot->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Farm plot removed.',
            'data' => ['id' => $id],
        ]);
    }

    /**
     * True when any existing plot's coordinates are within COLLISION_RADIUS_METERS.
     */
    private function hasCoordinateCollision(float $latitude, float $longitude): bool
    {
        $hit = DB::selectOne(
            'SELECT id
             FROM farm_plots
             WHERE deleted_at IS NULL
               AND coordinates IS NOT NULL
               AND ST_Distance_Sphere(coordinates, POINT(?, ?)) <= ?
             LIMIT 1',
            [$longitude, $latitude, self::COLLISION_RADIUS_METERS]
        );

        return $hit !== null;
    }
}
