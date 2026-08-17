<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

/**
 * Overwrites tbl_barangays lat/lng with precise micro-location pins
 * so the Open-Meteo bulk fetcher queries distinct cells per barangay.
 *
 * Dataset format (CSV):  Name, "latitude, longitude"
 * Paste the remaining 54 rows into $coordinates using the same pattern.
 */
class BarangayCoordinateSeeder extends Seeder
{
    /**
     * CSV short names → official names already stored by BarangaySeeder.
     *
     * @var array<string, string>
     */
    private const NAME_ALIASES = [
        'Cabugao' => 'Cabugao (Poblacion)',
        'San Manuel' => 'San Manuel (formerly Atelan)',
        'Silauan Norte' => 'Silauan Norte (Poblacion)',
        'Silauan Sur' => 'Silauan Sur (Poblacion)',
        'Soyung' => 'Soyung (Poblacion)',
        'Taggappan' => 'Taggappan (Poblacion)',
    ];

    public function run(): void
    {
        foreach ($this->coordinates() as $name => $coordinateString) {
            [$latitude, $longitude] = $this->parseCoordinateString($coordinateString);

            if ($latitude === null || $longitude === null) {
                continue;
            }

            Barangay::updateOrCreate(
                ['name' => $this->resolveName($name)],
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * First 10 Echague pins — paste the remaining 54 in the same "lat, lng" format.
     *
     * @return array<string, string>
     */
    protected function coordinates(): array
    {
        return [
            'Angoluan' => '16.720884693540548, 121.66533693003596',
            'Annafunan' => '16.711183667476295, 121.72236749238249',
            'Arabiat' => '16.641828, 121.6146',
            'Aromin' => '16.697098, 121.7648',
            'Babaran' => '16.681824, 121.7179',
            'Bacradal' => '16.628169, 121.6898',
            'Benguet' => '16.635877, 121.8773',
            'Buneg' => '16.708116, 121.6467',
            'Busilelao' => '16.674484, 121.6965',
            'Cabugao' => '16.706806, 121.6727',
        ];
    }

    /**
     * @return array{0:?float, 1:?float}
     */
    protected function parseCoordinateString(string $coordinateString): array
    {
        $parts = array_map('trim', explode(',', $coordinateString, 2));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [(float) $parts[0], (float) $parts[1]];
    }

    protected function resolveName(string $name): string
    {
        return self::NAME_ALIASES[$name] ?? $name;
    }
}
