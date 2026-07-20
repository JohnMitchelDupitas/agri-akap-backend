<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Multi-year crop monitoring records for the predictive analytics dashboard.
 * Covers 2022-2025 across the four main Echague commodities:
 * Rice (wet/dry), Corn (wet), Tobacco (dry), Vegetables (year-round).
 *
 * Yield values reflect realistic Isabela averages:
 *  - Rice:       3,500-4,500 kg/ha irrigated
 *  - Corn:       4,000-5,500 kg/ha
 *  - Tobacco:    1,200-1,600 kg/ha
 *  - Vegetables: 8,000-12,000 kg/ha
 *
 * 2024 records show reduced actual yields due to El Niño,
 * giving the linear-regression forecast visible trend data.
 */
class CropMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('crop_monitorings')->truncate();
        Schema::enableForeignKeyConstraints();

        $techId = DB::table('users')->where('email', 'tech@mao.com')->value('id');

        // Helper to build a record row.
        $row = function (
            string $plotId,
            string $crop,
            string $season,
            int $year,
            float $area,
            float $expected,
            ?float $actual,
            ?float $ph,
            ?string $stage
        ) use ($techId): array {
            return [
                'id'               => Str::uuid(),
                'farm_plot_id'     => $plotId,
                'technician_id'    => $techId,
                'crop_planted'     => $crop,
                'season'           => $season,
                'year'             => $year,
                'soil_ph'          => $ph,
                'area_planted_ha'  => $area,
                'expected_yield_kg'=> $expected,
                'actual_yield_kg'  => $actual,
                'crop_stage'       => $stage,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        };

        DB::table('crop_monitorings')->insert([

            // ── Rice plots – San Fabian (Eduardo Corpuz b1000001) ─────────────────
            $row('b1000001-0000-0000-0000-000000000001', 'Rice', 'Wet', 2022, 2.0,  8800, 8200, 6.2, 'Harvested'),
            $row('b1000001-0000-0000-0000-000000000001', 'Rice', 'Wet', 2023, 2.0,  9000, 8700, 6.1, 'Harvested'),
            $row('b1000001-0000-0000-0000-000000000001', 'Rice', 'Wet', 2024, 2.0,  9000, 5400, 6.0, 'Harvested'), // El Niño drop
            $row('b1000001-0000-0000-0000-000000000001', 'Rice', 'Wet', 2025, 2.0,  9200, null, 6.2, 'Vegetative'),

            // ── Corn plot – San Fabian (Eduardo Corpuz b1000002) ──────────────────
            $row('b1000002-0000-0000-0000-000000000002', 'Corn', 'Dry', 2022, 1.0,  5200, 5000, 6.0, 'Harvested'),
            $row('b1000002-0000-0000-0000-000000000002', 'Corn', 'Dry', 2023, 1.0,  5300, 5100, 5.9, 'Harvested'),
            $row('b1000002-0000-0000-0000-000000000002', 'Corn', 'Dry', 2024, 1.0,  5300, 3200, 5.8, 'Harvested'), // El Niño
            $row('b1000002-0000-0000-0000-000000000002', 'Corn', 'Dry', 2025, 1.0,  5400, null, 6.0, 'Tasseling'),

            // ── Rice – San Fabian (Marilyn Tumibay b1000003) ──────────────────────
            $row('b1000003-0000-0000-0000-000000000003', 'Rice', 'Wet', 2022, 1.5,  6300, 5800, 5.9, 'Harvested'),
            $row('b1000003-0000-0000-0000-000000000003', 'Rice', 'Wet', 2023, 1.5,  6500, 6100, 6.0, 'Harvested'),
            $row('b1000003-0000-0000-0000-000000000003', 'Rice', 'Wet', 2024, 1.5,  6500, 4000, 5.8, 'Harvested'), // El Niño
            $row('b1000003-0000-0000-0000-000000000003', 'Rice', 'Wet', 2025, 1.5,  6600, null, 6.0, 'Vegetative'),

            // ── Corn – Garit Norte (Renato Caguioa b1000004) ─────────────────
            $row('b1000004-0000-0000-0000-000000000004', 'Corn', 'Wet', 2022, 2.5, 13000, 12500, 6.1, 'Harvested'),
            $row('b1000004-0000-0000-0000-000000000004', 'Corn', 'Wet', 2023, 2.5, 13500, 13100, 6.2, 'Harvested'),
            $row('b1000004-0000-0000-0000-000000000004', 'Corn', 'Wet', 2024, 2.5, 13500,  8200, 6.0, 'Harvested'), // El Niño + FAW
            $row('b1000004-0000-0000-0000-000000000004', 'Corn', 'Wet', 2025, 2.5, 13800,  null, 6.1, 'Vegetative'),

            // ── Rice – Garit Norte (Teresita Palma b1000005) ─────────────────
            $row('b1000005-0000-0000-0000-000000000005', 'Rice', 'Wet', 2022, 1.0,  4200, 4000, 6.0, 'Harvested'),
            $row('b1000005-0000-0000-0000-000000000005', 'Rice', 'Wet', 2023, 1.0,  4300, 4200, 6.0, 'Harvested'),
            $row('b1000005-0000-0000-0000-000000000005', 'Rice', 'Wet', 2024, 1.0,  4300, 2600, 5.8, 'Harvested'), // El Niño
            $row('b1000005-0000-0000-0000-000000000005', 'Rice', 'Wet', 2025, 1.0,  4400,  null, 6.0, 'Flowering'),

            // ── Rice – Fugu (Artemio Baquiran b1000006) ───────────────────────
            $row('b1000006-0000-0000-0000-000000000006', 'Rice', 'Wet', 2022, 3.0, 13200, 12600, 6.2, 'Harvested'),
            $row('b1000006-0000-0000-0000-000000000006', 'Rice', 'Wet', 2023, 3.0, 13500, 13000, 6.1, 'Harvested'),
            $row('b1000006-0000-0000-0000-000000000006', 'Rice', 'Wet', 2024, 3.0, 13500,  7800, 6.0, 'Harvested'), // El Niño
            $row('b1000006-0000-0000-0000-000000000006', 'Rice', 'Wet', 2025, 3.0, 13800,  null, 6.2, 'Ripening'),

            // ── Tobacco – Fugu (Artemio Baquiran b1000007) ────────────────────
            $row('b1000007-0000-0000-0000-000000000007', 'Tobacco', 'Dry', 2022, 1.0, 1500, 1480, 5.8, 'Harvested'),
            $row('b1000007-0000-0000-0000-000000000007', 'Tobacco', 'Dry', 2023, 1.0, 1550, 1500, 5.9, 'Harvested'),
            $row('b1000007-0000-0000-0000-000000000007', 'Tobacco', 'Dry', 2024, 1.0, 1550, 1280, 5.7, 'Harvested'), // TMV loss
            $row('b1000007-0000-0000-0000-000000000007', 'Tobacco', 'Dry', 2025, 1.0, 1600,  null, 5.8, 'Topping'),

            // ── Rice – Soyung (Poblacion) (Felicito Talosig b1000008) ────────────────────
            $row('b1000008-0000-0000-0000-000000000008', 'Rice', 'Wet', 2022, 2.0,  8600, 8300, 6.0, 'Harvested'),
            $row('b1000008-0000-0000-0000-000000000008', 'Rice', 'Wet', 2023, 2.0,  8800, 8600, 6.1, 'Harvested'),
            $row('b1000008-0000-0000-0000-000000000008', 'Rice', 'Wet', 2024, 2.0,  8800, 6200, 5.9, 'Harvested'),
            $row('b1000008-0000-0000-0000-000000000008', 'Rice', 'Wet', 2025, 2.0,  9000,  null, 6.0, 'Vegetative'),

            // ── Vegetables – Soyung (Poblacion) (Gloria Ferrer b1000009) ──────────────────
            $row('b1000009-0000-0000-0000-000000000009', 'Vegetables', 'Year-Round', 2022, 1.0, 10000, 9500, 6.5, 'Harvested'),
            $row('b1000009-0000-0000-0000-000000000009', 'Vegetables', 'Year-Round', 2023, 1.0, 10500, 10200, 6.5, 'Harvested'),
            $row('b1000009-0000-0000-0000-000000000009', 'Vegetables', 'Year-Round', 2024, 1.0, 10500, 8800, 6.4, 'Harvested'),
            $row('b1000009-0000-0000-0000-000000000009', 'Vegetables', 'Year-Round', 2025, 1.0, 11000, null, 6.5, 'Growing'),

            // ── Tobacco – Mabbayad (Rogelio Buenaobra b1000010) ──────────────
            $row('b1000010-0000-0000-0000-000000000010', 'Tobacco', 'Dry', 2022, 2.0, 3000, 2900, 5.7, 'Harvested'),
            $row('b1000010-0000-0000-0000-000000000010', 'Tobacco', 'Dry', 2023, 2.0, 3100, 2980, 5.8, 'Harvested'),
            $row('b1000010-0000-0000-0000-000000000010', 'Tobacco', 'Dry', 2024, 2.0, 3100, 2200, 5.7, 'Harvested'), // TMV loss
            $row('b1000010-0000-0000-0000-000000000010', 'Tobacco', 'Dry', 2025, 2.0, 3200,  null, 5.8, 'Transplanted'),

            // ── Rice – Mabbayad (Rogelio Buenaobra b1000011) ─────────────────
            $row('b1000011-0000-0000-0000-000000000011', 'Rice', 'Wet', 2022, 1.0,  4300, 4100, 6.0, 'Harvested'),
            $row('b1000011-0000-0000-0000-000000000011', 'Rice', 'Wet', 2023, 1.0,  4400, 4300, 6.0, 'Harvested'),
            $row('b1000011-0000-0000-0000-000000000011', 'Rice', 'Wet', 2024, 1.0,  4400, 2900, 5.9, 'Harvested'),
            $row('b1000011-0000-0000-0000-000000000011', 'Rice', 'Wet', 2025, 1.0,  4500,  null, 6.0, 'Flowering'),

            // ── Rice – Magleticia (Danilo Turingan b1000012) ────────────────────
            $row('b1000012-0000-0000-0000-000000000012', 'Rice', 'Wet', 2022, 3.0, 13200, 12900, 6.2, 'Harvested'),
            $row('b1000012-0000-0000-0000-000000000012', 'Rice', 'Wet', 2023, 3.0, 13500, 13200, 6.2, 'Harvested'),
            $row('b1000012-0000-0000-0000-000000000012', 'Rice', 'Wet', 2024, 3.0, 13500,  8100, 6.0, 'Harvested'), // El Niño + BPH
            $row('b1000012-0000-0000-0000-000000000012', 'Rice', 'Wet', 2025, 3.0, 13800,  null, 6.2, 'Vegetative'),

            // ── Corn – Magleticia (Vilma Batoon b1000013) ───────────────────────
            $row('b1000013-0000-0000-0000-000000000013', 'Corn', 'Wet', 2022, 1.5,  7800, 7500, 6.0, 'Harvested'),
            $row('b1000013-0000-0000-0000-000000000013', 'Corn', 'Wet', 2023, 1.5,  8000, 7900, 6.1, 'Harvested'),
            $row('b1000013-0000-0000-0000-000000000013', 'Corn', 'Wet', 2024, 1.5,  8000, 5500, 5.9, 'Harvested'),
            $row('b1000013-0000-0000-0000-000000000013', 'Corn', 'Wet', 2025, 1.5,  8200,  null, 6.0, 'Tasseling'),

            // ── Corn – Pag-asa (Jaime Manantan b1000014) ───────────────────────
            $row('b1000014-0000-0000-0000-000000000014', 'Corn', 'Wet', 2022, 2.0, 10200, 9800, 5.9, 'Harvested'),
            $row('b1000014-0000-0000-0000-000000000014', 'Corn', 'Wet', 2023, 2.0, 10500, 10100, 6.0, 'Harvested'),
            $row('b1000014-0000-0000-0000-000000000014', 'Corn', 'Wet', 2024, 2.0, 10500,  7200, 5.8, 'Harvested'), // Typhoon
            $row('b1000014-0000-0000-0000-000000000014', 'Corn', 'Wet', 2025, 2.0, 10800,  null, 6.0, 'Vegetative'),

            // ── Rice – San Antonio Ugad (Nimrod Carpio b1000015) ────────────────────
            $row('b1000015-0000-0000-0000-000000000015', 'Rice', 'Wet', 2022, 2.5, 10750, 10200, 6.1, 'Harvested'),
            $row('b1000015-0000-0000-0000-000000000015', 'Rice', 'Wet', 2023, 2.5, 11000, 10800, 6.1, 'Harvested'),
            $row('b1000015-0000-0000-0000-000000000015', 'Rice', 'Wet', 2024, 2.5, 11000,  7700, 6.0, 'Harvested'),
            $row('b1000015-0000-0000-0000-000000000015', 'Rice', 'Wet', 2025, 2.5, 11250,  null, 6.1, 'Ripening'),

            // ── Tobacco – San Antonio Ugad (Nimrod Carpio b1000016) ────────────────
            $row('b1000016-0000-0000-0000-000000000016', 'Tobacco', 'Dry', 2022, 1.0, 1500, 1450, 5.7, 'Harvested'),
            $row('b1000016-0000-0000-0000-000000000016', 'Tobacco', 'Dry', 2023, 1.0, 1550, 1500, 5.8, 'Harvested'),
            $row('b1000016-0000-0000-0000-000000000016', 'Tobacco', 'Dry', 2024, 1.0, 1550, 1350, 5.7, 'Harvested'),
            $row('b1000016-0000-0000-0000-000000000016', 'Tobacco', 'Dry', 2025, 1.0, 1600,  null, 5.8, 'Topping'),

            // ── Corn – Rumang-ay (Elvira Aguinaldo b1000017) ──────────────────
            $row('b1000017-0000-0000-0000-000000000017', 'Corn', 'Wet', 2022, 1.5,  7500, 7100, 6.0, 'Harvested'),
            $row('b1000017-0000-0000-0000-000000000017', 'Corn', 'Wet', 2023, 1.5,  7700, 7500, 6.0, 'Harvested'),
            $row('b1000017-0000-0000-0000-000000000017', 'Corn', 'Wet', 2024, 1.5,  7700,  4200, 5.8, 'Harvested'), // El Niño
            $row('b1000017-0000-0000-0000-000000000017', 'Corn', 'Wet', 2025, 1.5,  7900,  null, 6.0, 'Vegetative'),

            // ── Corn – Narra (Roberto Cabanatan b1000018) ─────────────────
            $row('b1000018-0000-0000-0000-000000000018', 'Corn', 'Wet', 2022, 2.0, 10000,  9700, 6.1, 'Harvested'),
            $row('b1000018-0000-0000-0000-000000000018', 'Corn', 'Wet', 2023, 2.0, 10200, 10000, 6.1, 'Harvested'),
            $row('b1000018-0000-0000-0000-000000000018', 'Corn', 'Wet', 2024, 2.0, 10200,  6800, 5.9, 'Harvested'), // Typhoon
            $row('b1000018-0000-0000-0000-000000000018', 'Corn', 'Wet', 2025, 2.0, 10500,  null, 6.1, 'Vegetative'),

            // ── Vegetables – Gumbauan (Priscilla Tallungan b1000019) ──────────
            $row('b1000019-0000-0000-0000-000000000019', 'Vegetables', 'Year-Round', 2022, 1.0,  9500,  9000, 6.4, 'Harvested'),
            $row('b1000019-0000-0000-0000-000000000019', 'Vegetables', 'Year-Round', 2023, 1.0, 10000,  9800, 6.5, 'Harvested'),
            $row('b1000019-0000-0000-0000-000000000019', 'Vegetables', 'Year-Round', 2024, 1.0, 10000,  8200, 6.3, 'Harvested'),
            $row('b1000019-0000-0000-0000-000000000019', 'Vegetables', 'Year-Round', 2025, 1.0, 10500,   null, 6.4, 'Growing'),

            // ── Rice – Gumbauan (Priscilla Tallungan b1000020) ────────────────
            $row('b1000020-0000-0000-0000-000000000020', 'Rice', 'Wet', 2022, 0.5,  2100, 1950, 6.0, 'Harvested'),
            $row('b1000020-0000-0000-0000-000000000020', 'Rice', 'Wet', 2023, 0.5,  2200, 2100, 6.0, 'Harvested'),
            $row('b1000020-0000-0000-0000-000000000020', 'Rice', 'Wet', 2024, 0.5,  2200, 1500, 5.9, 'Harvested'),
            $row('b1000020-0000-0000-0000-000000000020', 'Rice', 'Wet', 2025, 0.5,  2300,  null, 6.0, 'Vegetative'),

        ]);
    }
}
