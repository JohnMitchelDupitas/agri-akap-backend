<?php

namespace App\Http\Controllers;

use App\Models\CropMonitoring;
use App\Models\DamageAssessment;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\PestOutbreak;
use App\Models\Program;
use App\Models\ReportWorkflow;
use App\Services\ReportAggregationService;
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

        $pendingReports = ReportWorkflow::where('provincial_status', 'Pending')->count();

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
                    'pending_reports' => $pendingReports,
                ],
                'audit_trail' => $recentTransactions,
            ],
        ], 200);
    }

    /**
     * Generate data for the Accomplishment Report (Phase 5 - Executive Reporting).
     * Delegates to ReportAggregationService for filter-aware aggregation.
     */
    public function accomplishmentReport(ReportAggregationService $aggregation): JsonResponse
    {
        $payload = $aggregation->aggregate([
            'report_type' => 'Provincial Accomplishment Report',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $payload,
        ]);
    }

    /**
     * Personal contribution history for the active reporting period (technician).
     */
    public function activityLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');
        $techId = $request->user()->id;

        $distributions = Distribution::with([
            'farmer:id,first_name,surname,permanent_brgy',
            'program:id,name,unit_of_measurement',
        ])
            ->where('distributed_by', $techId)
            ->where('status', 'claimed')
            ->whereDate('claimed_at', '>=', $dateFrom)
            ->whereDate('claimed_at', '<=', $dateTo)
            ->orderByDesc('claimed_at')
            ->limit(25)
            ->get()
            ->map(fn ($d) => [
                'type' => 'distribution',
                'id' => $d->id,
                'date' => optional($d->claimed_at)->format('M d, Y h:i A'),
                'label' => optional($d->program)->name,
                'detail' => trim((optional($d->farmer)->first_name ?? '') . ' ' . (optional($d->farmer)->surname ?? '')),
                'barangay' => optional($d->farmer)->permanent_brgy,
                'quantity' => $d->quantity_claimed . ' ' . optional($d->program)->unit_of_measurement,
                'status' => $d->status,
            ]);

        $assessments = DamageAssessment::with([
            'farmer:id,first_name,surname,permanent_brgy',
            'farmPlot:id,commodity',
        ])
            ->where('technician_id', $techId)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($a) => [
                'type' => 'damage_assessment',
                'id' => $a->id,
                'date' => $a->created_at->format('M d, Y h:i A'),
                'label' => $a->calamity_name,
                'detail' => trim((optional($a->farmer)->first_name ?? '') . ' ' . (optional($a->farmer)->surname ?? '')),
                'barangay' => optional($a->farmer)->permanent_brgy,
                'quantity' => $a->damage_percentage . '% damage',
                'status' => $a->status,
            ]);

        $pests = PestOutbreak::with(['farmPlot:id,location_brgy,commodity'])
            ->where('technician_id', $techId)
            ->whereDate('date_spotted', '>=', $dateFrom)
            ->whereDate('date_spotted', '<=', $dateTo)
            ->orderByDesc('date_spotted')
            ->limit(25)
            ->get()
            ->map(fn ($p) => [
                'type' => 'pest_outbreak',
                'id' => $p->id,
                'date' => optional($p->date_spotted)->format('M d, Y'),
                'label' => $p->pest_name,
                'detail' => optional($p->farmPlot)->commodity,
                'barangay' => optional($p->farmPlot)->location_brgy,
                'quantity' => $p->severity,
                'status' => $p->status,
            ]);

        $distCount = Distribution::where('distributed_by', $techId)
            ->where('status', 'claimed')
            ->whereDate('claimed_at', '>=', $dateFrom)
            ->whereDate('claimed_at', '<=', $dateTo)
            ->count();

        $assessCount = DamageAssessment::where('technician_id', $techId)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $pestCount = PestOutbreak::where('technician_id', $techId)
            ->whereDate('date_spotted', '>=', $dateFrom)
            ->whereDate('date_spotted', '<=', $dateTo)
            ->count();

        $recent = $distributions
            ->concat($assessments)
            ->concat($pests)
            ->sortByDesc('date')
            ->take(30)
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                'summary' => [
                    'distributions' => $distCount,
                    'damage_assessments' => $assessCount,
                    'pest_outbreaks' => $pestCount,
                ],
                'recent' => $recent,
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
