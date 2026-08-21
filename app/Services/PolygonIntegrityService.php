<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * DA Polygon Integrity Validator
 *
 * Enforces two DA-RSBSA georeferencing rules for farm boundary polygons:
 *
 * 1. Start/End Gap Rule — the perimeter walk's first and last GPS points must
 *    be ≤ GAP_LIMIT_METERS apart. Exceeding this means the technician never
 *    returned close enough to the starting stake, leaving an unclosed polygon
 *    that the DA web platform rejects.
 *
 * 2. Spatial Overlap Rule — a new boundary must not intersect any existing
 *    farm boundary already on record. The DA-RSBSA platform rejects overlapping
 *    GPX tracks; detecting this server-side prevents a failed remote sync after
 *    the record has already been written locally.
 *
 * Implementation notes:
 * - `boundary_points` is stored as a JSON column (not a native GEOMETRY type),
 *   so overlap detection uses a two-phase approach:
 *     Phase 1: ST_Distance_Sphere on the existing POINT `coordinates` centroid
 *              column to quickly narrow candidates within the incoming polygon's
 *              bounding-circle radius (+ 50 m buffer).
 *     Phase 2: Full polygon-polygon intersection in PHP using:
 *              a) AABB (bounding-box) fast reject
 *              b) Ray-casting point-in-polygon for containment
 *              c) Segment-pair cross-product intersection for edge-crossing
 * - All geographic calculations use the equirectangular approximation (lat/lng
 *   treated as planar), which is accurate for the small scales of farm plots.
 */
class PolygonIntegrityService
{
    /** Maximum allowed distance (metres) between walk start and end points. */
    public const GAP_LIMIT_METERS = 10.0;

    // ─── Haversine ────────────────────────────────────────────────────────────

    /** Great-circle distance in metres between two WGS-84 coordinates. */
    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6_371_000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $R * asin(sqrt($a));
    }

    // ─── Gap Rule ─────────────────────────────────────────────────────────────

    /**
     * True when the perimeter walk's first and last vertices are more than
     * GAP_LIMIT_METERS apart — indicating the technician did not return within
     * range of the starting stake before closing the polygon.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     */
    public function hasUnclosedGap(array $points): bool
    {
        $n = count($points);
        if ($n < 2) {
            return false;
        }

        $first = $points[0];
        $last  = $points[$n - 1];

        return $this->haversineMeters(
            (float) $first['lat'], (float) $first['lng'],
            (float) $last['lat'],  (float) $last['lng'],
        ) > self::GAP_LIMIT_METERS;
    }

    // ─── Geometry Helpers ─────────────────────────────────────────────────────

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public function boundingBox(array $points): array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'minLat' => (float) min($lats), 'maxLat' => (float) max($lats),
            'minLng' => (float) min($lngs), 'maxLng' => (float) max($lngs),
        ];
    }

    /**
     * Mean-coordinate centroid.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{lat: float, lng: float}
     */
    public function centroid(array $points): array
    {
        $n = count($points);

        return [
            'lat' => array_sum(array_column($points, 'lat')) / $n,
            'lng' => array_sum(array_column($points, 'lng')) / $n,
        ];
    }

    /**
     * Radius (metres) of the smallest circle centred at the centroid that
     * contains all polygon vertices — used as the ST_Distance_Sphere search
     * radius when querying candidate existing plots.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     */
    public function boundingRadius(array $points): float
    {
        $c      = $this->centroid($points);
        $maxR   = 0.0;

        foreach ($points as $p) {
            $d = $this->haversineMeters(
                (float) $c['lat'], (float) $c['lng'],
                (float) $p['lat'], (float) $p['lng'],
            );
            if ($d > $maxR) {
                $maxR = $d;
            }
        }

        return $maxR;
    }

    // ─── Polygon Intersection ─────────────────────────────────────────────────

    /**
     * Ray-casting (even-odd rule) point-in-polygon test.
     * Works for both clockwise and counter-clockwise vertex ordering.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     */
    public function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $n      = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $polygon[$i]['lng'];
            $yi = (float) $polygon[$i]['lat'];
            $xj = (float) $polygon[$j]['lng'];
            $yj = (float) $polygon[$j]['lat'];

            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Signed area of triangle(o, a, b) via the 2-D cross-product.
     * Positive = CCW, negative = CW, zero = collinear.
     *
     * @param  array{lat: float, lng: float}  $o
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private function cross(array $o, array $a, array $b): float
    {
        return ((float) $a['lng'] - (float) $o['lng']) * ((float) $b['lat'] - (float) $o['lat'])
             - ((float) $a['lat'] - (float) $o['lat']) * ((float) $b['lng'] - (float) $o['lng']);
    }

    /**
     * True when segments p1→p2 and p3→p4 properly intersect (each endpoint
     * lies strictly on opposite sides of the other segment's line).
     * Collinear/touching endpoints are treated as non-intersecting since those
     * are caught by the point-in-polygon pass.
     *
     * @param  array{lat: float, lng: float}  $p1
     * @param  array{lat: float, lng: float}  $p2
     * @param  array{lat: float, lng: float}  $p3
     * @param  array{lat: float, lng: float}  $p4
     */
    private function segmentsIntersect(array $p1, array $p2, array $p3, array $p4): bool
    {
        $d1 = $this->cross($p3, $p4, $p1);
        $d2 = $this->cross($p3, $p4, $p2);
        $d3 = $this->cross($p1, $p2, $p3);
        $d4 = $this->cross($p1, $p2, $p4);

        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
            && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    /**
     * Full polygon-polygon intersection test.
     *
     * Three-phase algorithm:
     * 1. AABB fast reject — polygons whose bounding boxes don't overlap cannot intersect.
     * 2. Point-in-polygon — any vertex of A inside B (or B inside A) confirms overlap/containment.
     * 3. Edge-pair intersection — catches X-crossing where no vertex of one polygon lies inside
     *    the other (e.g. two polygons intersecting like a plus sign).
     *
     * @param  array<int, array{lat: float, lng: float}>  $a
     * @param  array<int, array{lat: float, lng: float}>  $b
     */
    public function polygonsIntersect(array $a, array $b): bool
    {
        $bbA = $this->boundingBox($a);
        $bbB = $this->boundingBox($b);

        // Phase 1 — AABB fast reject
        if ($bbA['maxLat'] < $bbB['minLat'] || $bbA['minLat'] > $bbB['maxLat'] ||
            $bbA['maxLng'] < $bbB['minLng'] || $bbA['minLng'] > $bbB['maxLng']) {
            return false;
        }

        // Phase 2 — point-in-polygon (handles containment and most partial overlaps)
        foreach ($a as $p) {
            if ($this->pointInPolygon((float) $p['lat'], (float) $p['lng'], $b)) {
                return true;
            }
        }

        foreach ($b as $p) {
            if ($this->pointInPolygon((float) $p['lat'], (float) $p['lng'], $a)) {
                return true;
            }
        }

        // Phase 3 — edge-pair intersection (handles X/T-crossing without vertex containment)
        $nA = count($a);
        $nB = count($b);

        for ($i = 0; $i < $nA; $i++) {
            $a1 = $a[$i];
            $a2 = $a[($i + 1) % $nA];

            for ($j = 0; $j < $nB; $j++) {
                $b1 = $b[$j];
                $b2 = $b[($j + 1) % $nB];

                if ($this->segmentsIntersect($a1, $a2, $b1, $b2)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ─── DB Query ─────────────────────────────────────────────────────────────

    /**
     * Find the first existing farm plot whose boundary intersects the given polygon.
     *
     * Phase 1 (SQL): narrow candidates via ST_Distance_Sphere on the centroid
     *   POINT `coordinates` column, searching within (bounding-circle radius × 2 + 50 m).
     * Phase 2 (PHP): decode each candidate's `boundary_points` JSON and run the
     *   full polygon-polygon intersection test.
     *
     * Returns the first colliding plot's `id` and `farmer_id`, or null if clear.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points        Incoming polygon vertices
     * @param  string|null                                $excludePlotId  Skip this plot (for updates)
     */
    public function findOverlappingPlot(array $points, ?string $excludePlotId = null): ?object
    {
        $c            = $this->centroid($points);
        $radius       = $this->boundingRadius($points);
        // Generous buffer: two bounding-circles can be tangent and still intersect if both
        // are very elongated, so we use 2× radius + 50 m as the pre-filter distance.
        $searchRadius = max($radius * 2 + 50.0, 100.0);

        $sql = 'SELECT id, farmer_id, boundary_points
                FROM farm_plots
                WHERE deleted_at IS NULL
                  AND boundary_points IS NOT NULL
                  AND coordinates IS NOT NULL
                  AND ST_Distance_Sphere(coordinates, POINT(?, ?)) <= ?'
            . ($excludePlotId ? ' AND id != ?' : '')
            . ' LIMIT 200';

        $bindings = $excludePlotId
            ? [(float) $c['lng'], (float) $c['lat'], $searchRadius, $excludePlotId]
            : [(float) $c['lng'], (float) $c['lat'], $searchRadius];

        $candidates = DB::select($sql, $bindings);

        foreach ($candidates as $row) {
            $raw = $row->boundary_points;
            $existing = is_string($raw) ? json_decode($raw, true) : (array) $raw;

            if (! is_array($existing) || count($existing) < 3) {
                continue;
            }

            // Normalise: accept both [{lat,lng}] and [[lat, lng]] formats.
            $existing = array_map(static function ($p) {
                if (is_array($p) && isset($p['lat'], $p['lng'])) {
                    return ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
                }
                if (is_array($p) && isset($p[0], $p[1])) {
                    return ['lat' => (float) $p[0], 'lng' => (float) $p[1]];
                }

                return null;
            }, $existing);

            $existing = array_values(array_filter($existing));
            if (count($existing) < 3) {
                continue;
            }

            if ($this->polygonsIntersect($points, $existing)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Normalise a raw coordinates payload (from request or JSON column) into
     * the canonical `[{lat: float, lng: float}, ...]` format used throughout
     * this service. Returns null if the payload cannot be interpreted.
     *
     * Accepted input shapes:
     *   - JSON string (decoded automatically)
     *   - [{lat, lng}, ...]
     *   - [[lat, lng], ...]
     *
     * @return array<int, array{lat: float, lng: float}>|null
     */
    public function normalisePoints(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $raw = $decoded;
        }

        if (! is_array($raw)) {
            return null;
        }

        $points = [];

        foreach ($raw as $p) {
            if (is_array($p) && isset($p['lat'], $p['lng'])) {
                $points[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
            } elseif (is_array($p) && isset($p[0], $p[1])) {
                $points[] = ['lat' => (float) $p[0], 'lng' => (float) $p[1]];
            }
        }

        return count($points) >= 3 ? $points : null;
    }
}
