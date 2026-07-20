<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Damage assessments filed by the field technician across Echague barangays.
 * Covers typical Isabela calamities: typhoons, flooding, and El Niño drought.
 * Provides data for the GIS heatmap, damage review workflow, and report exports.
 *
 * Coordinates are the same as the farm plots so heatmap pins land correctly.
 */
class DamageAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('damage_assessments')->truncate();
        Schema::enableForeignKeyConstraints();

        // Fetch system user IDs (set by UserSeeder via HasUuid trait).
        $techId  = DB::table('users')->where('email', 'tech@mao.com')->value('id');
        $adminId = DB::table('users')->where('email', 'admin@mao.com')->value('id');
        $brgyId  = DB::table('users')->where('email', 'brgy@mao.com')->value('id');

        DB::table('damage_assessments')->insert([

            // 1 – Typhoon Egay (2025) – San Fabian rice plot (Eduardo Corpuz)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000001-0000-0000-0000-000000000001',
                'farmer_id'           => 'a1000001-0000-0000-0000-000000000001',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 85.00,
                'estimated_value_lost'=> 68000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6842,
                'longitude'           => 121.6715,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 26, 10, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2025, 7, 28, 14, 30),
                'remarks'             => 'Full lodging of rice crop; partial harvest possible.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 25),
                'updated_at'          => Carbon::create(2025, 7, 28),
            ],

            // 2 – Typhoon Egay – San Fabian corn plot (Eduardo Corpuz)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000002-0000-0000-0000-000000000002',
                'farmer_id'           => 'a1000001-0000-0000-0000-000000000001',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 60.00,
                'estimated_value_lost'=> 22000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6860,
                'longitude'           => 121.6730,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 26, 11, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2025, 7, 28, 15, 0),
                'remarks'             => 'Stalk breakage; estimated 60% yield loss.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 25),
                'updated_at'          => Carbon::create(2025, 7, 28),
            ],

            // 3 – Typhoon Egay – San Fabian rice (Marilyn Tumibay)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000003-0000-0000-0000-000000000003',
                'farmer_id'           => 'a1000002-0000-0000-0000-000000000002',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 90.00,
                'estimated_value_lost'=> 51750.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6831,
                'longitude'           => 121.6696,
                'status'              => 'Verified',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 27, 9, 0),
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => 'Complete inundation for 4 days.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 26),
                'updated_at'          => Carbon::create(2025, 7, 27),
            ],

            // 4 – El Niño 2024 – Garit Norte corn (Renato Caguioa)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000004-0000-0000-0000-000000000004',
                'farmer_id'           => 'a1000003-0000-0000-0000-000000000003',
                'technician_id'       => $techId,
                'calamity_name'       => 'El Niño Drought 2024',
                'date_of_calamity'    => Carbon::create(2024, 3, 10),
                'damage_percentage'   => 70.00,
                'estimated_value_lost'=> 57750.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6918,
                'longitude'           => 121.6572,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2024, 3, 14, 8, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2024, 3, 17, 10, 0),
                'remarks'             => 'Prolonged dry spell; irrigation unavailable.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2024, 3, 12),
                'updated_at'          => Carbon::create(2024, 3, 17),
            ],

            // 5 – El Niño 2024 – Garit Norte rice (Teresita Palma)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000005-0000-0000-0000-000000000005',
                'farmer_id'           => 'a1000004-0000-0000-0000-000000000004',
                'technician_id'       => $techId,
                'calamity_name'       => 'El Niño Drought 2024',
                'date_of_calamity'    => Carbon::create(2024, 3, 10),
                'damage_percentage'   => 55.00,
                'estimated_value_lost'=> 18975.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6895,
                'longitude'           => 121.6551,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2024, 3, 14, 8, 30),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2024, 3, 17, 11, 0),
                'remarks'             => 'Senior citizen farmer; priority relief recommended.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2024, 3, 12),
                'updated_at'          => Carbon::create(2024, 3, 17),
            ],

            // 6 – Typhoon Egay – Fugu rice (Artemio Baquiran)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000006-0000-0000-0000-000000000006',
                'farmer_id'           => 'a1000005-0000-0000-0000-000000000005',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 95.00,
                'estimated_value_lost'=> 142500.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6625,
                'longitude'           => 121.6804,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 27, 13, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2025, 7, 29, 9, 0),
                'remarks'             => 'Near-total loss; riverside location flooded completely.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 26),
                'updated_at'          => Carbon::create(2025, 7, 29),
            ],

            // 7 – Flooding 2025 – Soyung (Poblacion) rice (Felicito Talosig)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000008-0000-0000-0000-000000000008',
                'farmer_id'           => 'a1000006-0000-0000-0000-000000000006',
                'technician_id'       => $techId,
                'calamity_name'       => 'Monsoon Flood Aug 2025',
                'date_of_calamity'    => Carbon::create(2025, 8, 5),
                'damage_percentage'   => 40.00,
                'estimated_value_lost'=> 30800.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7134,
                'longitude'           => 121.6768,
                'status'              => 'Verified',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 8, 8, 9, 0),
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => 'Partial flooding; late-stage crop less severely hit.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 8, 6),
                'updated_at'          => Carbon::create(2025, 8, 8),
            ],

            // 8 – Typhoon Egay – Mabbayad tobacco (Rogelio Buenaobra)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000010-0000-0000-0000-000000000010',
                'farmer_id'           => 'a1000008-0000-0000-0000-000000000008',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 75.00,
                'estimated_value_lost'=> 87000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7258,
                'longitude'           => 121.6541,
                'status'              => 'Pending',
                'verified_by'         => null,
                'verified_at'         => null,
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => null,
                'device_id'           => 'TECH-MOBILE-001',
                'created_at'          => Carbon::create(2025, 7, 26),
                'updated_at'          => Carbon::create(2025, 7, 26),
            ],

            // 9 – El Niño 2024 – Magleticia rice (Danilo Turingan)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000012-0000-0000-0000-000000000012',
                'farmer_id'           => 'a1000009-0000-0000-0000-000000000009',
                'technician_id'       => $techId,
                'calamity_name'       => 'El Niño Drought 2024',
                'date_of_calamity'    => Carbon::create(2024, 3, 10),
                'damage_percentage'   => 50.00,
                'estimated_value_lost'=> 52500.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7308,
                'longitude'           => 121.7022,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2024, 3, 15, 10, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2024, 3, 18, 9, 0),
                'remarks'             => 'Irrigation system unable to cope with demand.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2024, 3, 13),
                'updated_at'          => Carbon::create(2024, 3, 18),
            ],

            // 10 – Typhoon Egay – Pag-asa corn (Jaime Manantan)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000014-0000-0000-0000-000000000014',
                'farmer_id'           => 'a1000011-0000-0000-0000-000000000011',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 65.00,
                'estimated_value_lost'=> 39000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7001,
                'longitude'           => 121.6438,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 28, 8, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2025, 7, 30, 10, 0),
                'remarks'             => 'Upland plot; wind damage primary cause.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 26),
                'updated_at'          => Carbon::create(2025, 7, 30),
            ],

            // 11 – Monsoon flood – San Antonio Ugad rice (Nimrod Carpio)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000015-0000-0000-0000-000000000015',
                'farmer_id'           => 'a1000012-0000-0000-0000-000000000012',
                'technician_id'       => $techId,
                'calamity_name'       => 'Monsoon Flood Aug 2025',
                'date_of_calamity'    => Carbon::create(2025, 8, 5),
                'damage_percentage'   => 30.00,
                'estimated_value_lost'=> 26250.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7319,
                'longitude'           => 121.6475,
                'status'              => 'Pending',
                'verified_by'         => null,
                'verified_at'         => null,
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => null,
                'device_id'           => 'TECH-MOBILE-001',
                'created_at'          => Carbon::create(2025, 8, 7),
                'updated_at'          => Carbon::create(2025, 8, 7),
            ],

            // 12 – El Niño 2024 – Rumang-ay corn (Elvira Aguinaldo)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000017-0000-0000-0000-000000000017',
                'farmer_id'           => 'a1000013-0000-0000-0000-000000000013',
                'technician_id'       => $techId,
                'calamity_name'       => 'El Niño Drought 2024',
                'date_of_calamity'    => Carbon::create(2024, 3, 10),
                'damage_percentage'   => 80.00,
                'estimated_value_lost'=> 44000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7831,
                'longitude'           => 121.6962,
                'status'              => 'Approved',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2024, 3, 16, 11, 0),
                'approved_by'         => $adminId,
                'approved_at'         => Carbon::create(2024, 3, 19, 9, 0),
                'remarks'             => 'Remote upland; no irrigation; senior citizen farmer.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2024, 3, 14),
                'updated_at'          => Carbon::create(2024, 3, 19),
            ],

            // 13 – Typhoon Egay – Narra corn (Roberto Cabanatan)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000018-0000-0000-0000-000000000018',
                'farmer_id'           => 'a1000014-0000-0000-0000-000000000014',
                'technician_id'       => $techId,
                'calamity_name'       => 'Typhoon Egay',
                'date_of_calamity'    => Carbon::create(2025, 7, 24),
                'damage_percentage'   => 55.00,
                'estimated_value_lost'=> 33000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.7758,
                'longitude'           => 121.7072,
                'status'              => 'Verified',
                'verified_by'         => $brgyId,
                'verified_at'         => Carbon::create(2025, 7, 28, 14, 0),
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => 'Moderately affected; recovery possible with timely inputs.',
                'device_id'           => null,
                'created_at'          => Carbon::create(2025, 7, 27),
                'updated_at'          => Carbon::create(2025, 7, 28),
            ],

            // 14 – Flooding – Gumbauan vegetable (Priscilla Tallungan)
            [
                'id'                  => Str::uuid(),
                'farm_plot_id'        => 'b1000019-0000-0000-0000-000000000019',
                'farmer_id'           => 'a1000015-0000-0000-0000-000000000015',
                'technician_id'       => $techId,
                'calamity_name'       => 'Monsoon Flood Aug 2025',
                'date_of_calamity'    => Carbon::create(2025, 8, 5),
                'damage_percentage'   => 45.00,
                'estimated_value_lost'=> 18000.00,
                'photo_evidence_path' => null,
                'latitude'            => 16.6683,
                'longitude'           => 121.6631,
                'status'              => 'Pending',
                'verified_by'         => null,
                'verified_at'         => null,
                'approved_by'         => null,
                'approved_at'         => null,
                'remarks'             => null,
                'device_id'           => 'TECH-MOBILE-001',
                'created_at'          => Carbon::create(2025, 8, 7),
                'updated_at'          => Carbon::create(2025, 8, 7),
            ],

        ]);
    }
}
