<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\PestMonitoring;
use App\Models\PlantingLog;
use App\Models\WeatherCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4-Tier Enterprise Analytics engine:
 * Descriptive → Diagnostic → Predictive → Prescriptive
 */
class AnalyticsController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => '4-tier analytics dashboard payload.',
            'data' => [
                'descriptive' => $this->descriptive(),
                'diagnostic' => $this->diagnostic(),
                'predictive' => $this->predictive(),
                'prescriptive' => $this->prescriptive(),
            ],
        ]);
    }

    /**
     * What happened? Aggregate registry, planted area, subsidy outflow.
     */
    private function descriptive(): array
    {
        $totalFarmers = Farmer::query()->count();

        $totalHectares = 0.0;
        if (Schema::hasTable('planting_logs')) {
            $totalHectares = (float) PlantingLog::query()->sum('area_planted');
        }

        $totalSubsidyItems = (float) Distribution::query()->sum('quantity_claimed');
        $totalSubsidyClaims = Distribution::query()->count();

        $subsidiesByBarangay = Distribution::query()
            ->join('farmers', 'distributions.farmer_id', '=', 'farmers.id')
            ->whereNull('farmers.deleted_at')
            ->select(
                'farmers.permanent_brgy as barangay',
                DB::raw('COUNT(*) as claim_count'),
                DB::raw('COALESCE(SUM(distributions.quantity_claimed), 0) as total_quantity')
            )
            ->groupBy('farmers.permanent_brgy')
            ->orderByDesc('total_quantity')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'barangay' => $row->barangay ?: 'Unspecified',
                'claim_count' => (int) $row->claim_count,
                'total_quantity' => round((float) $row->total_quantity, 2),
            ])
            ->values()
            ->all();

        return [
            'total_farmers' => $totalFarmers,
            'total_hectares_planted' => round($totalHectares, 2),
            'total_subsidy_items' => round($totalSubsidyItems, 2),
            'total_subsidy_claims' => $totalSubsidyClaims,
            'subsidies_by_barangay' => $subsidiesByBarangay,
        ];
    }

    /**
     * Why did it happen? Pest vulnerability by crop stage × barangay.
     */
    private function diagnostic(): array
    {
        if (! Schema::hasTable('pest_monitoring')) {
            return [
                'by_crop_stage' => [],
                'by_barangay' => [],
                'matrix' => [],
            ];
        }

        $hasCropStage = Schema::hasColumn('pest_monitoring', 'crop_stage');

        $stageExpr = $hasCropStage
            ? "COALESCE(NULLIF(pest_monitoring.crop_stage, ''), 'Unspecified')"
            : "'Unspecified'";

        $byCropStage = DB::table('pest_monitoring')
            ->selectRaw("{$stageExpr} as crop_stage, COUNT(*) as total")
            ->groupByRaw('1')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'crop_stage' => $r->crop_stage,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();

        $byBarangay = DB::table('pest_monitoring')
            ->leftJoin('farmers', 'pest_monitoring.farmer_id', '=', 'farmers.id')
            ->selectRaw("COALESCE(farmers.permanent_brgy, 'Unspecified') as barangay")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN pest_monitoring.is_outbreak = 1 THEN 1 ELSE 0 END) as outbreaks')
            ->groupByRaw('1')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'barangay' => $r->barangay,
                'total' => (int) $r->total,
                'outbreaks' => (int) $r->outbreaks,
            ])
            ->values()
            ->all();

        $matrix = DB::table('pest_monitoring')
            ->leftJoin('farmers', 'pest_monitoring.farmer_id', '=', 'farmers.id')
            ->selectRaw("{$stageExpr} as crop_stage")
            ->selectRaw("COALESCE(farmers.permanent_brgy, 'Unspecified') as barangay")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('1, 2')
            ->orderByDesc('total')
            ->limit(40)
            ->get()
            ->map(fn ($r) => [
                'crop_stage' => $r->crop_stage,
                'barangay' => $r->barangay,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();

        return [
            'by_crop_stage' => $byCropStage,
            'by_barangay' => $byBarangay,
            'matrix' => $matrix,
        ];
    }

    /**
     * What will happen? Active plantings → estimated harvest windows.
     */
    private function predictive(): array
    {
        if (! Schema::hasTable('planting_logs')) {
            return ['upcoming_harvests' => [], 'by_barangay' => []];
        }

        $logs = PlantingLog::query()
            ->with('farmer:id,surname,first_name,permanent_brgy')
            ->where('status', 'Active')
            ->orderBy('date_planted')
            ->limit(200)
            ->get();

        $upcoming = [];
        foreach ($logs as $log) {
            $days = $this->cropCycleDays($log->crop_type);
            $planted = $log->date_planted ? Carbon::parse($log->date_planted) : null;
            if (! $planted) {
                continue;
            }
            $harvest = $planted->copy()->addDays($days);
            $upcoming[] = [
                'planting_log_id' => $log->id,
                'farmer_name' => trim(($log->farmer?->surname ?? '').', '.($log->farmer?->first_name ?? '')),
                'barangay' => $log->farmer?->permanent_brgy ?: 'Unspecified',
                'crop_type' => $log->crop_type,
                'variety' => $log->variety,
                'area_planted' => (float) $log->area_planted,
                'date_planted' => $planted->toDateString(),
                'estimated_harvest_date' => $harvest->toDateString(),
                'days_to_harvest' => (int) Carbon::today()->diffInDays($harvest, false),
                'cycle_days' => $days,
            ];
        }

        usort($upcoming, fn ($a, $b) => strcmp($a['estimated_harvest_date'], $b['estimated_harvest_date']));

        $byBarangay = collect($upcoming)
            ->groupBy('barangay')
            ->map(function ($rows, $barangay) {
                return [
                    'barangay' => $barangay,
                    'count' => $rows->count(),
                    'total_area_ha' => round($rows->sum('area_planted'), 2),
                    'next_harvest_date' => $rows->min('estimated_harvest_date'),
                    'items' => $rows->take(8)->values()->all(),
                ];
            })
            ->sortBy('next_harvest_date')
            ->values()
            ->all();

        return [
            'upcoming_harvests' => array_slice($upcoming, 0, 50),
            'by_barangay' => $byBarangay,
        ];
    }

    /**
     * What should we do? Relief / SMS recommendations from damage + weather.
     */
    private function prescriptive(): array
    {
        $actions = [];

        // High damage assessments → buffer seed allocation
        $damageByBrgy = DamageAssessment::query()
            ->leftJoin('farmers', 'damage_assessments.farmer_id', '=', 'farmers.id')
            ->leftJoin('farm_plots', 'damage_assessments.farm_plot_id', '=', 'farm_plots.id')
            ->select(
                DB::raw("COALESCE(farmers.permanent_brgy, farm_plots.location_brgy, 'Unspecified') as barangay"),
                DB::raw('COUNT(*) as reports'),
                DB::raw('AVG(damage_assessments.damage_percentage) as avg_damage'),
                DB::raw('COALESCE(SUM(damage_assessments.area_destroyed_ha), 0) as area_ha')
            )
            ->where(function ($q) {
                $q->where('damage_assessments.damage_percentage', '>=', 30)
                    ->orWhere('damage_assessments.area_destroyed_ha', '>=', 0.5);
            })
            ->groupBy(DB::raw("COALESCE(farmers.permanent_brgy, farm_plots.location_brgy, 'Unspecified')"))
            ->havingRaw('AVG(damage_assessments.damage_percentage) >= 25 OR SUM(damage_assessments.area_destroyed_ha) >= 1')
            ->orderByDesc('avg_damage')
            ->limit(12)
            ->get();

        foreach ($damageByBrgy as $row) {
            $bags = max(20, (int) ceil(((float) $row->area_ha) * 40)); // ~40 kg bags/ha heuristic
            $actions[] = [
                'id' => 'relief-'.$row->barangay,
                'type' => 'allocate_relief',
                'priority' => ((float) $row->avg_damage) >= 50 ? 'critical' : 'high',
                'barangay' => $row->barangay,
                'title' => "Buffer seed allocation — {$row->barangay}",
                'recommendation' => sprintf(
                    'Recommend allocating %d buffer seed bags to %s (avg damage %.0f%% across %d assessments, %.2f ha affected).',
                    $bags,
                    $row->barangay,
                    (float) $row->avg_damage,
                    (int) $row->reports,
                    (float) $row->area_ha
                ),
                'cta' => 'Allocate Relief',
                'meta' => [
                    'buffer_bags' => $bags,
                    'avg_damage' => round((float) $row->avg_damage, 1),
                    'reports' => (int) $row->reports,
                ],
            ];
        }

        // Weather risk → SMS advisory
        if (Schema::hasTable('tbl_weather_cache')) {
            $weatherRisk = WeatherCache::query()
                ->whereDate('forecast_date', '>=', Carbon::today())
                ->whereDate('forecast_date', '<=', Carbon::today()->addDays(3))
                ->where(function ($q) {
                    $q->where('precipitation_probability', '>=', 80)
                        ->orWhere('temperature_max', '>=', 38)
                        ->orWhere('wind_speed_10m', '>', 15);
                })
                ->select(
                    'barangay_name',
                    DB::raw('MAX(precipitation_probability) as max_rain'),
                    DB::raw('MAX(temperature_max) as max_temp'),
                    DB::raw('MAX(wind_speed_10m) as max_wind')
                )
                ->groupBy('barangay_name')
                ->orderByDesc('max_rain')
                ->limit(10)
                ->get();

            foreach ($weatherRisk as $w) {
                $drivers = [];
                if ((int) $w->max_rain >= 80) {
                    $drivers[] = "rain {$w->max_rain}%";
                }
                if ((float) $w->max_temp >= 38) {
                    $drivers[] = "heat {$w->max_temp}°C";
                }
                if ((float) $w->max_wind > 15) {
                    $drivers[] = "wind {$w->max_wind} km/h";
                }

                $actions[] = [
                    'id' => 'weather-'.$w->barangay_name,
                    'type' => 'sms_advisory',
                    'priority' => ((int) $w->max_rain >= 90 || (float) $w->max_temp >= 40) ? 'critical' : 'medium',
                    'barangay' => $w->barangay_name,
                    'title' => "Weather advisory — {$w->barangay_name}",
                    'recommendation' => sprintf(
                        'Draft SMS advisory for %s: elevated risk (%s) in the next 72 hours. Advise farmers to secure inputs and delay spraying if wind is high.',
                        $w->barangay_name,
                        implode(', ', $drivers) ?: 'adverse weather'
                    ),
                    'cta' => 'Draft SMS Advisory',
                    'meta' => [
                        'max_rain' => (int) $w->max_rain,
                        'max_temp' => (float) $w->max_temp,
                        'max_wind' => (float) $w->max_wind,
                    ],
                ];
            }
        }

        // Active pest outbreaks without recent intervention
        if (Schema::hasTable('pest_monitoring')) {
            $outbreaks = PestMonitoring::query()
                ->leftJoin('farmers', 'pest_monitoring.farmer_id', '=', 'farmers.id')
                ->where('pest_monitoring.is_outbreak', true)
                ->select(
                    DB::raw("COALESCE(farmers.permanent_brgy, 'Unspecified') as barangay"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('MAX(pest_monitoring.pest_name) as pest_name')
                )
                ->groupBy(DB::raw("COALESCE(farmers.permanent_brgy, 'Unspecified')"))
                ->having('total', '>=', 1)
                ->orderByDesc('total')
                ->limit(8)
                ->get();

            foreach ($outbreaks as $o) {
                $actions[] = [
                    'id' => 'pest-'.$o->barangay,
                    'type' => 'sms_advisory',
                    'priority' => ((int) $o->total) >= 3 ? 'high' : 'medium',
                    'barangay' => $o->barangay,
                    'title' => "Outbreak response — {$o->barangay}",
                    'recommendation' => sprintf(
                        'Recommend allocating spray teams / biocontrol to %s (%d outbreak reports%s). Draft community advisory before peak incidence.',
                        $o->barangay,
                        (int) $o->total,
                        $o->pest_name ? ", primary pest: {$o->pest_name}" : ''
                    ),
                    'cta' => 'Draft SMS Advisory',
                    'meta' => [
                        'outbreak_count' => (int) $o->total,
                        'pest_name' => $o->pest_name,
                    ],
                ];
            }
        }

        usort($actions, function ($a, $b) {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return ($rank[$a['priority']] ?? 9) <=> ($rank[$b['priority']] ?? 9);
        });

        return [
            'actions' => array_values($actions),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function cropCycleDays(?string $cropType): int
    {
        $crop = strtolower(trim((string) $cropType));

        return match (true) {
            str_contains($crop, 'rice') => 110,
            str_contains($crop, 'corn') => 100,
            str_contains($crop, 'high') => 90,
            default => 105,
        };
    }
}
