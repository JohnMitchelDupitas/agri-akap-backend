<?php

namespace App\Http\Controllers;

use App\Models\CropMonitoring;
use App\Models\DamageAssessment;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\PestOutbreak;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Geospatial payload for the GIS map view.
     * Returns geotagged farm plots, damage points, and pest outbreaks.
     * Optional filters: ?barangay=&commodity=&layer=(farms|damage|pests)
     */
    public function mapData(Request $request): JsonResponse
    {
        $barangay = $request->query('barangay');
        $commodity = $request->query('commodity');
        $layer = $request->query('layer'); // null => all layers

        $wantFarms = !$layer || $layer === 'farms';
        $wantDamage = !$layer || $layer === 'damage';
        $wantPests = !$layer || $layer === 'pests';

        $farmPlots = [];
        if ($wantFarms) {
            $farmPlots = FarmPlot::with('farmer:id,first_name,surname')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($barangay, fn ($q) => $q->where('location_brgy', $barangay))
                ->when($commodity, fn ($q) => $q->where('commodity', $commodity))
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'commodity' => $p->commodity,
                    'size_ha' => $p->size_ha !== null ? (float) $p->size_ha : null,
                    'brgy' => $p->location_brgy,
                    'farmer_name' => trim((optional($p->farmer)->first_name ?? '') . ' ' . (optional($p->farmer)->surname ?? '')),
                ])
                ->values();
        }

        $damagePoints = [];
        if ($wantDamage) {
            $damagePoints = DamageAssessment::with([
                'farmer:id,first_name,surname,permanent_brgy',
                'farmPlot:id,commodity,location_brgy',
            ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($barangay, function ($q) use ($barangay) {
                    $q->where(function ($sub) use ($barangay) {
                        $sub->whereHas('farmPlot', fn ($fp) => $fp->where('location_brgy', $barangay))
                            ->orWhereHas('farmer', fn ($f) => $f->where('permanent_brgy', $barangay));
                    });
                })
                ->when($commodity, fn ($q) => $q->whereHas('farmPlot', fn ($fp) => $fp->where('commodity', $commodity)))
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'lat' => (float) $a->latitude,
                    'lng' => (float) $a->longitude,
                    'damage_percentage' => (float) $a->damage_percentage,
                    'calamity_name' => $a->calamity_name,
                    'status' => $a->status,
                    'commodity' => optional($a->farmPlot)->commodity,
                    'brgy' => optional($a->farmPlot)->location_brgy ?? optional($a->farmer)->permanent_brgy,
                    'farmer_name' => trim((optional($a->farmer)->first_name ?? '') . ' ' . (optional($a->farmer)->surname ?? '')),
                ])
                ->values();
        }

        $pestOutbreaks = [];
        if ($wantPests) {
            $pestOutbreaks = PestOutbreak::with([
                'farmPlot:id,commodity,location_brgy',
            ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($barangay, fn ($q) => $q->whereHas('farmPlot', fn ($fp) => $fp->where('location_brgy', $barangay)))
                ->when($commodity, fn ($q) => $q->whereHas('farmPlot', fn ($fp) => $fp->where('commodity', $commodity)))
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'pest_name' => $p->pest_name,
                    'severity' => $p->severity,
                    'status' => $p->status,
                    'commodity' => optional($p->farmPlot)->commodity,
                    'brgy' => optional($p->farmPlot)->location_brgy,
                ])
                ->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'farm_plots' => $farmPlots,
                'damage_points' => $damagePoints,
                'pest_outbreaks' => $pestOutbreaks,
            ],
        ]);
    }

    /**
     * Fetch high-level KPIs, recent audit trail, and damage summary
     * for the admin Mission Control dashboard.
     */
    public function getStats(): JsonResponse
    {
        $activeProgramsCount = Program::where('is_active', true)
            ->where('end_date', '>=', now())
            ->count();

        $totalFarmers = Farmer::count();

        $dispensedTotals = Distribution::join('programs', 'distributions.program_id', '=', 'programs.id')
            ->select(
                'programs.unit_of_measurement as unit',
                DB::raw('SUM(distributions.quantity_claimed) as total_dispensed')
            )
            ->where('distributions.status', 'claimed')
            ->groupBy('programs.unit_of_measurement')
            ->get();

        $damageSummary = [
            'total' => DamageAssessment::count(),
            'pending' => DamageAssessment::where('status', 'Pending')->count(),
            'verified' => DamageAssessment::where('status', 'Verified')->count(),
            'approved' => DamageAssessment::where('status', 'Approved')->count(),
        ];

        $activePests = PestOutbreak::where('status', 'Active')->count();

        $recentTransactions = Distribution::with([
            'farmer:id,first_name,surname,permanent_brgy',
            'program:id,name,unit_of_measurement',
            'technician:id,name',
        ])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->created_at->format('M d, Y h:i A'),
                'farmer_name' => optional($t->farmer)->first_name . ' ' . optional($t->farmer)->surname,
                'barangay' => optional($t->farmer)->permanent_brgy,
                'program_name' => optional($t->program)->name,
                'dispensed' => $t->quantity_claimed . ' ' . optional($t->program)->unit_of_measurement,
                'technician' => optional($t->technician)->name,
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'active_programs' => $activeProgramsCount,
                    'total_farmers' => $totalFarmers,
                    'dispensed_breakdown' => $dispensedTotals,
                    'damage_summary' => $damageSummary,
                    'active_pests' => $activePests,
                ],
                'audit_trail' => $recentTransactions,
            ],
        ], 200);
    }

    /**
     * Generate data for the Accomplishment Report (Phase 5 - Executive Reporting).
     * Returns a pre-aggregated payload suitable for a printable report.
     */
    public function accomplishmentReport(): JsonResponse
    {
        $programs = Program::withCount('distributions')
            ->with([
                'distributions' => fn ($q) => $q->select('id', 'program_id', 'quantity_claimed', 'farmer_id'),
            ])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'total_quantity' => $p->total_quantity,
                'remaining_quantity' => $p->remaining_quantity,
                'dispensed' => $p->total_quantity - $p->remaining_quantity,
                'beneficiaries' => $p->distributions_count,
                'unit' => $p->unit_of_measurement,
                'is_active' => $p->is_active,
                'start_date' => optional($p->start_date)->format('M d, Y'),
                'end_date' => optional($p->end_date)->format('M d, Y'),
                'funding_source' => $p->funding_source,
            ]);

        $damageAssessments = DamageAssessment::with([
            'farmer:id,first_name,surname,permanent_brgy',
            'farmPlot:id,commodity,size_ha,location_brgy',
        ])
            ->where('status', 'Approved')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'farmer_name' => optional($a->farmer)->first_name . ' ' . optional($a->farmer)->surname,
                'barangay' => optional($a->farmer)->permanent_brgy,
                'calamity_name' => $a->calamity_name,
                'date_of_calamity' => optional($a->date_of_calamity)->format('M d, Y'),
                'commodity' => optional($a->farmPlot)->commodity,
                'area_ha' => optional($a->farmPlot)->size_ha,
                'damage_percentage' => $a->damage_percentage,
                'estimated_value_lost' => $a->estimated_value_lost,
                'status' => $a->status,
            ]);

        $pestSummary = PestOutbreak::with([
            'farmPlot:id,location_brgy,commodity',
        ])
            ->orderBy('date_spotted', 'desc')
            ->get()
            ->groupBy(fn ($p) => $p->farmPlot->location_brgy ?? 'Unknown')
            ->map(fn ($group, $brgy) => [
                'barangay' => $brgy,
                'total_outbreaks' => $group->count(),
                'active' => $group->where('status', 'Active')->count(),
                'resolved' => $group->where('status', 'Resolved')->count(),
                'severities' => $group->groupBy('severity')->map->count(),
            ]);

        $farmersByBarangay = Farmer::selectRaw('permanent_brgy, COUNT(*) as count')
            ->groupBy('permanent_brgy')
            ->orderBy('permanent_brgy')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'generated_at' => now()->format('F j, Y g:i A'),
                'programs' => $programs,
                'damage_assessments' => $damageAssessments,
                'pest_summary_by_barangay' => $pestSummary,
                'farmers_by_barangay' => $farmersByBarangay,
                'totals' => [
                    'farmers' => Farmer::count(),
                    'programs' => Program::count(),
                    'distributions' => Distribution::count(),
                    'approved_damage_claims' => DamageAssessment::where('status', 'Approved')->count(),
                    'total_value_lost' => DamageAssessment::where('status', 'Approved')->sum('estimated_value_lost'),
                ],
            ],
        ]);
    }

    /**
     * Predictive analytics: crop-yield trend and next-season projection.
     * Uses a transparent least-squares linear trend over historical yield
     * per commodity. Falls back to expected yield when actual is missing.
     * Optional filter: ?commodity=
     */
    public function forecast(Request $request): JsonResponse
    {
        $commodityFilter = $request->query('commodity');

        $rows = CropMonitoring::query()
            ->when($commodityFilter, fn ($q) => $q->where('crop_planted', $commodityFilter))
            ->selectRaw(
                'crop_planted, year, ' .
                'SUM(COALESCE(actual_yield_kg, expected_yield_kg, 0)) as total_yield, ' .
                'SUM(COALESCE(area_planted_ha, 0)) as total_area, ' .
                'SUM(CASE WHEN actual_yield_kg IS NOT NULL THEN 1 ELSE 0 END) as yield_records, ' .
                'COUNT(*) as records'
            )
            ->groupBy('crop_planted', 'year')
            ->orderBy('crop_planted')
            ->orderBy('year')
            ->get();

        $commodities = [];
        foreach ($rows->groupBy('crop_planted') as $commodity => $series) {
            $history = $series
                ->map(function ($r) {
                    $ty = (float) $r->total_yield;
                    $ta = (float) $r->total_area;
                    return [
                        'year' => (int) $r->year,
                        'total_yield_kg' => round($ty, 2),
                        'total_area_ha' => round($ta, 2),
                        'yield_per_ha' => $ta > 0 ? round($ty / $ta, 2) : null,
                        'records' => (int) $r->records,
                        'yield_records' => (int) $r->yield_records,
                    ];
                })
                ->values();

            $points = $history
                ->filter(fn ($h) => $h['total_yield_kg'] > 0)
                ->map(fn ($h) => [(float) $h['year'], (float) $h['total_yield_kg']])
                ->values()
                ->all();

            $forecast = null;
            if (count($points) >= 2) {
                $reg = $this->linearRegression($points);
                if ($reg) {
                    $nextYear = (int) $history->max('year') + 1;
                    $projected = $reg['intercept'] + $reg['slope'] * $nextYear;
                    $projected = max(0, $projected);
                    $r2 = $reg['r2'];
                    $forecast = [
                        'year' => $nextYear,
                        'projected_yield_kg' => round($projected, 2),
                        'trend' => $reg['slope'] > 0 ? 'increasing' : ($reg['slope'] < 0 ? 'decreasing' : 'flat'),
                        'confidence' => $r2 >= 0.75 ? 'High' : ($r2 >= 0.4 ? 'Moderate' : 'Low'),
                        'r_squared' => round($r2, 3),
                        'method' => 'Least-squares linear trend',
                    ];
                }
            }

            $commodities[] = [
                'commodity' => $commodity,
                'history' => $history,
                'forecast' => $forecast,
                'note' => count($points) < 2
                    ? 'Insufficient yield history (need at least two years of data).'
                    : null,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'generated_at' => now()->format('F j, Y g:i A'),
                'commodities' => $commodities,
            ],
        ]);
    }

    /**
     * Agricultural risk index (0-100) per barangay + commodity, derived from
     * historical damage severity/frequency and active pest density.
     */
    public function riskIndex(Request $request): JsonResponse
    {
        $barangay = $request->query('barangay');
        $commodity = $request->query('commodity');

        $damage = DamageAssessment::query()
            ->join('farm_plots', 'damage_assessments.farm_plot_id', '=', 'farm_plots.id')
            ->when($barangay, fn ($q) => $q->where('farm_plots.location_brgy', $barangay))
            ->when($commodity, fn ($q) => $q->where('farm_plots.commodity', $commodity))
            ->selectRaw(
                'farm_plots.location_brgy as brgy, farm_plots.commodity as commodity, ' .
                'COUNT(*) as events, AVG(damage_assessments.damage_percentage) as avg_damage'
            )
            ->groupBy('farm_plots.location_brgy', 'farm_plots.commodity')
            ->get();

        $pests = PestOutbreak::query()
            ->join('farm_plots', 'pest_outbreaks.farm_plot_id', '=', 'farm_plots.id')
            ->where('pest_outbreaks.status', 'Active')
            ->when($barangay, fn ($q) => $q->where('farm_plots.location_brgy', $barangay))
            ->when($commodity, fn ($q) => $q->where('farm_plots.commodity', $commodity))
            ->selectRaw('farm_plots.location_brgy as brgy, farm_plots.commodity as commodity, COUNT(*) as active_pests')
            ->groupBy('farm_plots.location_brgy', 'farm_plots.commodity')
            ->get();

        $map = [];
        $keyFor = fn ($b, $c) => ($b ?? 'Unknown') . '||' . ($c ?? 'Unknown');

        foreach ($damage as $d) {
            $k = $keyFor($d->brgy, $d->commodity);
            $map[$k] = [
                'barangay' => $d->brgy ?? 'Unknown',
                'commodity' => $d->commodity ?? 'Unknown',
                'damage_events' => (int) $d->events,
                'avg_damage' => round((float) $d->avg_damage, 1),
                'active_pests' => 0,
            ];
        }
        foreach ($pests as $p) {
            $k = $keyFor($p->brgy, $p->commodity);
            if (!isset($map[$k])) {
                $map[$k] = [
                    'barangay' => $p->brgy ?? 'Unknown',
                    'commodity' => $p->commodity ?? 'Unknown',
                    'damage_events' => 0,
                    'avg_damage' => 0.0,
                    'active_pests' => 0,
                ];
            }
            $map[$k]['active_pests'] = (int) $p->active_pests;
        }

        $items = collect($map)->map(function ($row) {
            $damageScore = min($row['avg_damage'], 100);            // 0-100
            $pestScore = min($row['active_pests'] * 20, 100);        // each active outbreak +20
            $score = (int) round(0.6 * $damageScore + 0.4 * $pestScore);
            $level = $score >= 66 ? 'High' : ($score >= 33 ? 'Moderate' : 'Low');
            return array_merge($row, [
                'risk_score' => $score,
                'risk_level' => $level,
            ]);
        })
            ->sortByDesc('risk_score')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'generated_at' => now()->format('F j, Y g:i A'),
                'items' => $items,
                'formula' => 'risk = 0.6 * avg_damage% + 0.4 * min(active_pests * 20, 100)',
            ],
        ]);
    }

    /**
     * Ordinary least-squares linear regression.
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{slope: float, intercept: float, r2: float}|null
     */
    private function linearRegression(array $points): ?array
    {
        $n = count($points);
        if ($n < 2) {
            return null;
        }

        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($points as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denom = ($n * $sumXX) - ($sumX * $sumX);
        if ($denom == 0.0) {
            return null;
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // R-squared
        $meanY = $sumY / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($points as [$x, $y]) {
            $pred = $intercept + $slope * $x;
            $ssRes += ($y - $pred) ** 2;
            $ssTot += ($y - $meanY) ** 2;
        }
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 1.0;

        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2];
    }
}
