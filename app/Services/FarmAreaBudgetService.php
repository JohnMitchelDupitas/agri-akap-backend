<?php

namespace App\Services;

use App\Models\Farmer;
use App\Models\FarmPlot;
use Illuminate\Http\JsonResponse;

class FarmAreaBudgetService
{
    public const EPSILON = 0.0001;

    public function mappedHa(string $farmerId, ?string $excludePlotId = null): float
    {
        $query = FarmPlot::query()
            ->where('farmer_id', $farmerId)
            ->whereNull('deleted_at');

        if ($excludePlotId) {
            $query->where('id', '!=', $excludePlotId);
        }

        return (float) $query->sum('size_ha');
    }

    public function registeredHa(Farmer $farmer): float
    {
        return (float) ($farmer->total_farm_area_ha ?? 0);
    }

    public function remainingHa(Farmer $farmer, ?string $excludePlotId = null): float
    {
        return max(0.0, $this->registeredHa($farmer) - $this->mappedHa($farmer->id, $excludePlotId));
    }

    public function isMismatch(Farmer $farmer, ?float $mappedHa = null): bool
    {
        $mapped = $mappedHa ?? $this->mappedHa($farmer->id);
        $registered = $this->registeredHa($farmer);

        return $mapped > $registered + self::EPSILON;
    }

    /**
     * @return array{total_farm_area_ha: float, mapped_area_ha: float, remaining_ha: float, area_mismatch: bool}
     */
    public function summary(Farmer $farmer, ?string $excludePlotId = null): array
    {
        $mapped = $this->mappedHa($farmer->id, $excludePlotId);
        $registered = $this->registeredHa($farmer);

        return [
            'total_farm_area_ha' => $registered,
            'mapped_area_ha' => $mapped,
            'remaining_ha' => max(0.0, $registered - $mapped),
            'area_mismatch' => $mapped > $registered + self::EPSILON,
        ];
    }

    /**
     * Returns a 422 JsonResponse when over budget (unless discrepancy bypass is allowed).
     */
    public function assertWithinBudget(
        Farmer $farmer,
        float $sizeHa,
        ?string $excludePlotId = null,
        bool $allowDiscrepancy = false,
    ): ?JsonResponse {
        if ($allowDiscrepancy) {
            return null;
        }

        $registered = $this->registeredHa($farmer);
        $mappedOthers = $this->mappedHa($farmer->id, $excludePlotId);
        $projected = $mappedOthers + $sizeHa;
        $remaining = max(0.0, $registered - $mappedOthers);

        if ($projected > $registered + self::EPSILON) {
            return response()->json([
                'status' => 'error',
                'error' => 'Area Budget Exceeded',
                'message' => sprintf(
                    'Mapped area would become %.4f ha, exceeding the registered farm area of %.4f ha. Remaining unallocated quota: %.4f ha.',
                    $projected,
                    $registered,
                    $remaining,
                ),
                'data' => [
                    'total_farm_area_ha' => $registered,
                    'mapped_area_ha' => $mappedOthers,
                    'remaining_ha' => $remaining,
                    'requested_ha' => $sizeHa,
                    'projected_ha' => $projected,
                ],
            ], 422);
        }

        return null;
    }

    /**
     * Compute registered quota from enrollment plot payloads.
     *
     * @param  array<int, array<string, mixed>>  $plots
     */
    public function quotaFromRegistrationPlots(array $plots): float
    {
        $sum = 0.0;
        foreach ($plots as $plot) {
            $parcel = (float) ($plot['total_parcel_area_ha'] ?? 0);
            $size = (float) ($plot['size_ha'] ?? 0);
            $sum += $parcel > 0 ? $parcel : $size;
        }

        return round($sum, 4);
    }
}
