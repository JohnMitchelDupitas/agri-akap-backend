<?php

namespace App\Http\Controllers;

use App\Models\FarmPlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FarmPlotController extends Controller
{
    /** Collision radius in meters — blocks double-claim of the same parcel. */
    private const COLLISION_RADIUS_METERS = 15;

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
     * Aborts with 409 if coordinates fall within 15m of an existing plot.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farmer_id' => 'required|uuid|exists:farmers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_brgy' => 'required|string|max:100',
            'location_city' => 'nullable|string|max:100',
            'location_province' => 'nullable|string|max:100',
            'ownership_type' => 'required|in:Owner,Tenant,Lessee,Registered Owner',
            'landowner_name' => 'nullable|string|max:255',
            'size_ha' => 'required|numeric|min:0.0001|max:9999',
            'commodity' => 'required|string|max:100',
            'farm_type' => 'nullable|string|max:100',
            'proof_of_ownership_document' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        if ($this->hasCoordinateCollision($lat, $lng)) {
            return response()->json([
                'error' => 'Coordinate Collision',
                'message' => 'This land parcel is already registered to another user. Please verify tenancy/ownership.',
            ], 409);
        }

        $ownership = $validated['ownership_type'] === 'Owner'
            ? 'Registered Owner'
            : $validated['ownership_type'];

        $landownerName = $validated['landowner_name']
            ?? ($ownership === 'Registered Owner' ? null : $validated['landowner_name'] ?? null);

        $plot = DB::transaction(function () use ($validated, $lat, $lng, $ownership, $landownerName) {
            $id = (string) Str::uuid();
            $size = (float) $validated['size_ha'];

            DB::table('farm_plots')->insert([
                'id' => $id,
                'farmer_id' => $validated['farmer_id'],
                'location_brgy' => $validated['location_brgy'],
                'location_city' => $validated['location_city'] ?? 'Echague',
                'location_province' => $validated['location_province'] ?? 'Isabela',
                'latitude' => $lat,
                'longitude' => $lng,
                'total_parcel_area_ha' => $size,
                'is_ancestral_domain' => false,
                'is_agrarian_reform_beneficiary' => false,
                'ownership_type' => $ownership,
                'landowner_name' => $landownerName,
                'proof_of_ownership_document' => $validated['proof_of_ownership_document'] ?? 'Geotag Field Capture',
                'commodity' => $validated['commodity'],
                'size_ha' => $size,
                'farm_type' => $validated['farm_type'] ?? 'Irrigated',
                'is_organic' => false,
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Bind spatial POINT (lng, lat) for future ST_Distance_Sphere checks
            DB::update(
                'UPDATE farm_plots SET coordinates = POINT(?, ?) WHERE id = ?',
                [$lng, $lat, $id]
            );

            return FarmPlot::with('farmer:id,first_name,surname,rsbsa_no,permanent_brgy')->findOrFail($id);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Farm plot geotagged successfully.',
            'data' => $plot,
        ], 201);
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
