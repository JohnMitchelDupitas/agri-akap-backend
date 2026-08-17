<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Database\Seeders\Concerns\EchagueBarangays;
use Illuminate\Database\Seeder;

/**
 * Seeds all 64 Echague barangays with approximate lat/lng offsets
 * around the municipal center (16.7118, 121.6603).
 * Precise pins are applied afterwards by BarangayCoordinateSeeder.
 */
class BarangaySeeder extends Seeder
{
    public const MUNICIPAL_LAT = 16.7118;

    public const MUNICIPAL_LNG = 121.6603;

    public function run(): void
    {
        foreach (EchagueBarangays::ALL as $index => $name) {
            [$lat, $lng] = $this->approximateCoordinates($index);

            Barangay::updateOrCreate(
                ['name' => $name],
                [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Spread barangays on a small grid (~±0.08°) around Echague center.
     *
     * @return array{0:float,1:float}
     */
    protected function approximateCoordinates(int $index): array
    {
        $cols = 8;
        $row = intdiv($index, $cols);
        $col = $index % $cols;

        // ~0.012° ≈ 1.3 km — enough for distinct Open-Meteo cells, still municipal-scale.
        $lat = self::MUNICIPAL_LAT + (($row - 3.5) * 0.012);
        $lng = self::MUNICIPAL_LNG + (($col - 3.5) * 0.012);

        return [round($lat, 7), round($lng, 7)];
    }
}
