<?php

namespace App\Services;

use App\Models\DamageAssessment;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\PestOutbreak;
use App\Models\Program;
use Illuminate\Support\Carbon;

/**
 * Aggregates municipal metrics for statutory LGU reports with optional
 * date, barangay, and commodity filters.
 */
class ReportAggregationService
{
    public function aggregate(array $params): array
    {
        $reportType = $params['report_type'] ?? 'Provincial Accomplishment Report';
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $barangay = $params['barangay'] ?? null;
        $commodity = $params['commodity'] ?? null;

        if ($reportType === 'Palay Situation Report') {
            $commodity = 'Rice';
        } elseif ($reportType === 'Corn Situation Report') {
            $commodity = 'Corn';
        }

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'barangay' => $barangay,
            'commodity' => $commodity,
        ];

        $programs = $this->programsSummary($dateFrom, $dateTo, $barangay, $commodity);
        $matrix = $this->programPerformanceMatrix($dateFrom, $dateTo, $barangay, $commodity);
        $damageAssessments = $this->damageAssessments($dateFrom, $dateTo, $barangay, $commodity);
        $pestSummary = $this->pestSummary($barangay, $commodity);
        $farmersByBarangay = $this->farmersByBarangay($barangay, $commodity);

        $standingAreaHa = null;
        if (in_array($reportType, ['Palay Situation Report', 'Corn Situation Report'], true)) {
            $standingAreaHa = $this->standingAreaHa($commodity, $barangay);
        }

        return [
            'generated_at' => now()->format('F j, Y g:i A'),
            'report_type' => $reportType,
            'period_label' => $this->periodLabel($dateFrom, $dateTo),
            'filters_applied' => $filters,
            'standing_area_ha' => $standingAreaHa,
            'programs' => $programs,
            'program_performance_matrix' => $matrix,
            'damage_assessments' => $damageAssessments,
            'pest_summary_by_barangay' => $pestSummary,
            'farmers_by_barangay' => $farmersByBarangay,
            'totals' => [
                'farmers' => $this->farmerCount($barangay, $commodity),
                'programs' => count($programs),
                'distributions' => $this->distributionCount($dateFrom, $dateTo, $barangay, $commodity),
                'approved_damage_claims' => count($damageAssessments),
                'total_value_lost' => collect($damageAssessments)->sum('estimated_value_lost'),
                'damage_area_ha' => collect($damageAssessments)->sum('area_destroyed_ha'),
            ],
        ];
    }

    private function periodLabel(?string $from, ?string $to): string
    {
        if ($from && $to) {
            return Carbon::parse($from)->format('M d, Y') . ' – ' . Carbon::parse($to)->format('M d, Y');
        }
        if ($from) {
            return 'From ' . Carbon::parse($from)->format('M d, Y');
        }
        if ($to) {
            return 'Through ' . Carbon::parse($to)->format('M d, Y');
        }

        return now()->format('Y');
    }

    private function programsSummary(?string $dateFrom, ?string $dateTo, ?string $barangay, ?string $commodity): array
    {
        return Program::withCount([
            'distributions' => fn ($q) => $this->applyDistributionFilters($q, $dateFrom, $dateTo, $barangay, $commodity),
        ])
            ->get()
            ->map(function ($p) use ($dateFrom, $dateTo, $barangay, $commodity) {
                $dispensedQuery = Distribution::where('program_id', $p->id)
                    ->where('status', 'claimed');
                $this->applyDistributionFilters($dispensedQuery, $dateFrom, $dateTo, $barangay, $commodity);
                $dispensed = (int) $dispensedQuery->sum('quantity_claimed');

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'total_quantity' => $p->total_quantity,
                    'remaining_quantity' => $p->remaining_quantity,
                    'dispensed' => $dispensed,
                    'beneficiaries' => $p->distributions_count,
                    'unit' => $p->unit_of_measurement,
                    'is_active' => $p->is_active,
                    'start_date' => optional($p->start_date)->format('M d, Y'),
                    'end_date' => optional($p->end_date)->format('M d, Y'),
                    'funding_source' => $p->funding_source,
                ];
            })
            ->values()
            ->all();
    }

    private function programPerformanceMatrix(?string $dateFrom, ?string $dateTo, ?string $barangay, ?string $commodity): array
    {
        return Program::all()
            ->map(function ($p) use ($dateFrom, $dateTo, $barangay, $commodity) {
                $distQuery = Distribution::where('program_id', $p->id)->where('status', 'claimed');
                $this->applyDistributionFilters($distQuery, $dateFrom, $dateTo, $barangay, $commodity);
                $actual = (int) $distQuery->sum('quantity_claimed');
                $beneficiaries = (int) (clone $distQuery)->distinct('farmer_id')->count('farmer_id');
                $target = (int) $p->total_quantity;
                $pct = $target > 0 ? round(($actual / $target) * 100, 1) : 0;

                return [
                    'program_name' => $p->name,
                    'program_type' => $p->type,
                    'target_quantity' => $target,
                    'actual_dispensed' => $actual,
                    'unit' => $p->unit_of_measurement,
                    'beneficiaries' => $beneficiaries,
                    'accomplishment_pct' => $pct,
                ];
            })
            ->filter(fn ($row) => $row['target_quantity'] > 0 || $row['actual_dispensed'] > 0)
            ->values()
            ->all();
    }

    private function damageAssessments(?string $dateFrom, ?string $dateTo, ?string $barangay, ?string $commodity): array
    {
        $query = DamageAssessment::with([
            'farmer:id,first_name,surname,permanent_brgy',
            'farmPlot:id,commodity,size_ha,location_brgy',
        ])->whereIn('status', ['Approved', 'Claimed']);

        if ($dateFrom) {
            $query->whereDate('date_of_calamity', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date_of_calamity', '<=', $dateTo);
        }
        if ($barangay) {
            $query->where(function ($q) use ($barangay) {
                $q->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $barangay))
                    ->orWhereHas('farmPlot', fn ($fp) => $fp->where('location_brgy', $barangay));
            });
        }
        if ($commodity) {
            $query->whereHas('farmPlot', fn ($fp) => $fp->where('commodity', $commodity));
        }

        return $query->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'farmer_name' => trim((optional($a->farmer)->first_name ?? '') . ' ' . (optional($a->farmer)->surname ?? '')),
                'barangay' => optional($a->farmer)->permanent_brgy,
                'calamity_name' => $a->calamity_name,
                'calamity_type' => $a->calamity_type,
                'date_of_calamity' => optional($a->date_of_calamity)->format('M d, Y'),
                'commodity' => optional($a->farmPlot)->commodity,
                'area_ha' => optional($a->farmPlot)->size_ha,
                'area_destroyed_ha' => $a->area_destroyed_ha,
                'damage_percentage' => $a->damage_percentage,
                'estimated_value_lost' => $a->estimated_value_lost,
                'status' => $a->status,
            ])
            ->values()
            ->all();
    }

    private function pestSummary(?string $barangay, ?string $commodity): array
    {
        $query = PestOutbreak::with(['farmPlot:id,location_brgy,commodity']);

        if ($barangay) {
            $query->whereHas('farmPlot', fn ($q) => $q->where('location_brgy', $barangay));
        }
        if ($commodity) {
            $query->whereHas('farmPlot', fn ($q) => $q->where('commodity', $commodity));
        }

        return $query->orderBy('date_spotted', 'desc')
            ->get()
            ->groupBy(fn ($p) => $p->farmPlot->location_brgy ?? 'Unknown')
            ->map(fn ($group, $brgy) => [
                'barangay' => $brgy,
                'total_outbreaks' => $group->count(),
                'active' => $group->where('status', 'Active')->count(),
                'resolved' => $group->where('status', 'Resolved')->count(),
                'severities' => $group->groupBy('severity')->map->count(),
            ])
            ->values()
            ->all();
    }

    private function farmersByBarangay(?string $barangay, ?string $commodity): array
    {
        $query = Farmer::query();

        if ($barangay) {
            $query->where('permanent_brgy', $barangay);
        }
        if ($commodity) {
            $query->whereHas('farmPlots', fn ($q) => $q->where('commodity', $commodity));
        }

        return $query->selectRaw('permanent_brgy, COUNT(*) as count')
            ->groupBy('permanent_brgy')
            ->orderBy('permanent_brgy')
            ->get()
            ->map(fn ($r) => ['permanent_brgy' => $r->permanent_brgy, 'count' => (int) $r->count])
            ->values()
            ->all();
    }

    private function farmerCount(?string $barangay, ?string $commodity): int
    {
        $query = Farmer::query();
        if ($barangay) {
            $query->where('permanent_brgy', $barangay);
        }
        if ($commodity) {
            $query->whereHas('farmPlots', fn ($q) => $q->where('commodity', $commodity));
        }

        return $query->count();
    }

    private function distributionCount(?string $dateFrom, ?string $dateTo, ?string $barangay, ?string $commodity): int
    {
        $query = Distribution::where('status', 'claimed');
        $this->applyDistributionFilters($query, $dateFrom, $dateTo, $barangay, $commodity);

        return $query->count();
    }

    private function standingAreaHa(?string $commodity, ?string $barangay): float
    {
        $query = FarmPlot::query();
        if ($commodity) {
            $query->where('commodity', $commodity);
        }
        if ($barangay) {
            $query->where('location_brgy', $barangay);
        }

        return round((float) $query->sum('size_ha'), 4);
    }

    private function applyDistributionFilters($query, ?string $dateFrom, ?string $dateTo, ?string $barangay, ?string $commodity): void
    {
        if ($dateFrom) {
            $query->whereDate('claimed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('claimed_at', '<=', $dateTo);
        }
        if ($barangay) {
            $query->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $barangay));
        }
        if ($commodity) {
            $query->whereHas('farmer.farmPlots', fn ($fp) => $fp->where('commodity', $commodity));
        }
    }
}
