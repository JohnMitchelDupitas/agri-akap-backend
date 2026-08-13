<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessment;
use App\Models\PestMonitoring;
use App\Models\PlantingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExecutiveReportingController extends Controller
{
    /**
     * Aggregate barangay/technician-encoded planting, pest, and damage rows
     * into MAO executive report table shape.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in([
                'crop_production',
                'masterlists',
                'pest_surveillance',
                'damage_calamity',
            ])],
            'barangay' => ['nullable', 'string'],
            'crop_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $rows = match ($validated['category']) {
            'crop_production', 'masterlists' => $this->plantingRows($validated),
            'pest_surveillance' => $this->pestRows($validated),
            'damage_calamity' => $this->damageRows($validated),
        };

        return response()->json([
            'status' => 'success',
            'data' => [
                'category' => $validated['category'],
                'count' => count($rows),
                'rows' => $rows,
            ],
        ]);
    }

    private function plantingRows(array $f): array
    {
        $query = PlantingLog::query()
            ->with([
                'farmer:id,rsbsa_no,surname,first_name,middle_name,ext_name,birthdate,permanent_house_no,permanent_street,permanent_brgy,permanent_city,permanent_province',
                'farmPlot:id,location_brgy,commodity,size_ha',
            ])
            ->orderBy('date_planted')
            ->orderBy('created_at');

        if (! empty($f['barangay'])) {
            $query->whereHas('farmer', fn ($q) => $q->where('permanent_brgy', $f['barangay']));
        }
        if (! empty($f['crop_type'])) {
            $query->where('crop_type', $f['crop_type']);
        }
        if (! empty($f['date_from'])) {
            $query->whereDate('date_planted', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate('date_planted', '<=', $f['date_to']);
        }

        return $query->limit(2000)->get()->map(function (PlantingLog $log) {
            $farmer = $log->farmer;

            return [
                'rsbsa_no' => $farmer?->rsbsa_no ?? '',
                'last_name' => $farmer?->surname ?? '',
                'first_name' => $farmer?->first_name ?? '',
                'middle_name' => $farmer?->middle_name ?? '',
                'ext_name' => $farmer?->ext_name ?? '',
                'birthday' => optional($farmer?->birthdate)->format('Y-m-d') ?? '',
                'farmer_address' => $this->farmerAddress($farmer),
                'farm_location' => $log->farm_location
                    ?: ($log->farmPlot?->location_brgy ?? $farmer?->permanent_brgy ?? ''),
                'area_planted' => number_format((float) $log->area_planted, 2, '.', ''),
                'date_of_planting' => optional($log->date_planted)->format('Y-m-d') ?? '',
                'source_of_water' => $log->water_source ?? '',
                'remarks' => $log->remarks ?? '',
                'barangay' => $farmer?->permanent_brgy ?? '',
                'crop_type' => $log->crop_type ?? '',
                'report_date' => optional($log->date_planted)->format('Y-m-d') ?? '',
            ];
        })->values()->all();
    }

    private function pestRows(array $f): array
    {
        $query = PestMonitoring::query()
            ->with([
                'farmer:id,rsbsa_no,surname,first_name,middle_name,ext_name,birthdate,permanent_house_no,permanent_street,permanent_brgy,permanent_city,permanent_province',
                'farmPlot:id,location_brgy,commodity,size_ha',
            ])
            ->orderByRaw('COALESCE(date_of_inspection, DATE(created_at))')
            ->orderBy('created_at');

        if (! empty($f['barangay'])) {
            $query->whereHas('farmer', fn ($q) => $q->where('permanent_brgy', $f['barangay']));
        }
        if (! empty($f['crop_type'])) {
            $query->where('crop', $f['crop_type']);
        }
        if (! empty($f['date_from'])) {
            $from = $f['date_from'];
            $query->where(function ($q) use ($from) {
                $q->whereDate('date_of_inspection', '>=', $from)
                    ->orWhere(function ($q2) use ($from) {
                        $q2->whereNull('date_of_inspection')->whereDate('created_at', '>=', $from);
                    });
            });
        }
        if (! empty($f['date_to'])) {
            $to = $f['date_to'];
            $query->where(function ($q) use ($to) {
                $q->whereDate('date_of_inspection', '<=', $to)
                    ->orWhere(function ($q2) use ($to) {
                        $q2->whereNull('date_of_inspection')->whereDate('created_at', '<=', $to);
                    });
            });
        }

        return $query->limit(2000)->get()->map(function (PestMonitoring $row) {
            $farmer = $row->farmer;
            $reportDate = optional($row->date_of_inspection)->format('Y-m-d')
                ?: optional($row->created_at)->format('Y-m-d');

            return [
                'rsbsa_no' => $farmer?->rsbsa_no ?? '',
                'last_name' => $farmer?->surname ?? '',
                'first_name' => $farmer?->first_name ?? '',
                'middle_name' => $farmer?->middle_name ?? '',
                'ext_name' => $farmer?->ext_name ?? '',
                'birthday' => optional($farmer?->birthdate)->format('Y-m-d') ?? '',
                'farmer_address' => $this->farmerAddress($farmer),
                'farm_location' => $row->farm_location
                    ?: ($row->farmPlot?->location_brgy ?? $farmer?->permanent_brgy ?? ''),
                'area_planted' => $row->area_planted !== null
                    ? number_format((float) $row->area_planted, 2, '.', '')
                    : '',
                'days_after_planting' => $row->days_after_planting ?? '',
                'variety' => $row->variety ?? '',
                'area_damage_pct' => $row->area_damage_pct !== null
                    ? number_format((float) $row->area_damage_pct, 1, '.', '')
                    : (string) ($row->incidence ?? ''),
                'damage_by_pest' => $row->pest_name ?? '',
                'barangay' => $farmer?->permanent_brgy ?? '',
                'crop_type' => $row->crop ?? '',
                'report_date' => $reportDate ?? '',
            ];
        })->values()->all();
    }

    private function damageRows(array $f): array
    {
        $query = DamageAssessment::query()
            ->with([
                'farmer:id,rsbsa_no,surname,first_name,middle_name,ext_name,birthdate,permanent_house_no,permanent_street,permanent_brgy,permanent_city,permanent_province',
                'farmPlot:id,location_brgy,commodity,size_ha',
            ])
            ->orderBy('date_of_calamity')
            ->orderBy('created_at');

        if (! empty($f['barangay'])) {
            $brgy = $f['barangay'];
            $query->where(function ($q) use ($brgy) {
                $q->whereHas('farmer', fn ($f) => $f->where('permanent_brgy', $brgy))
                    ->orWhereHas('farmPlot', fn ($fp) => $fp->where('location_brgy', $brgy));
            });
        }
        if (! empty($f['crop_type'])) {
            $crop = $f['crop_type'];
            $query->whereHas('farmPlot', fn ($fp) => $fp->where('commodity', $crop));
        }
        if (! empty($f['date_from'])) {
            $query->whereDate('date_of_calamity', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate('date_of_calamity', '<=', $f['date_to']);
        }

        return $query->limit(2000)->get()->map(function (DamageAssessment $row) {
            $farmer = $row->farmer;
            $areaPlanted = $row->area_planted_ha ?? $row->farmPlot?->size_ha;

            return [
                'rsbsa_no' => $farmer?->rsbsa_no ?? '',
                'last_name' => $farmer?->surname ?? '',
                'first_name' => $farmer?->first_name ?? '',
                'middle_name' => $farmer?->middle_name ?? '',
                'ext_name' => $farmer?->ext_name ?? '',
                'farm_location' => $row->farmPlot?->location_brgy ?? $farmer?->permanent_brgy ?? '',
                'crop_type' => $row->farmPlot?->commodity ?? '',
                'stage_of_crop' => $row->crop_stage ?? '',
                'area_planted' => $areaPlanted !== null
                    ? number_format((float) $areaPlanted, 2, '.', '')
                    : '',
                'area_damaged' => $row->area_destroyed_ha !== null
                    ? number_format((float) $row->area_destroyed_ha, 2, '.', '')
                    : '',
                'est_yield_loss_pct' => $row->damage_percentage !== null
                    ? number_format((float) $row->damage_percentage, 1, '.', '')
                    : '',
                'barangay' => $farmer?->permanent_brgy ?? $row->farmPlot?->location_brgy ?? '',
                'report_date' => optional($row->date_of_calamity)->format('Y-m-d') ?? '',
            ];
        })->values()->all();
    }

    private function farmerAddress($farmer): string
    {
        if (! $farmer) {
            return '';
        }

        return collect([
            $farmer->permanent_house_no,
            $farmer->permanent_street,
            $farmer->permanent_brgy,
            $farmer->permanent_city,
            $farmer->permanent_province,
        ])->filter()->implode(', ');
    }
}
