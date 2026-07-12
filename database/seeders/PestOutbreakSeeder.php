<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Pest outbreak records across Echague barangay farm plots.
 * Covers pests common to Isabela: Brown Planthopper, Corn Earworm,
 * Tobacco Mosaic Virus, Fall Armyworm, and Golden Apple Snail (rice).
 * Provides data for the GIS map pest layer and risk-index analytics.
 */
class PestOutbreakSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('pest_outbreaks')->truncate();
        Schema::enableForeignKeyConstraints();

        $techId = DB::table('users')->where('email', 'tech@mao.com')->value('id');

        DB::table('pest_outbreaks')->insert([

            // 1 – Brown Planthopper – Bangag rice (Eduardo Corpuz plot 1)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000001-0000-0000-0000-000000000001',
                'technician_id'=> $techId,
                'pest_name'    => 'Brown Planthopper',
                'severity'     => 'High',
                'date_spotted' => Carbon::create(2025, 6, 15),
                'status'       => 'Contained',
                'latitude'     => 16.6842,
                'longitude'    => 121.6715,
                'notes'        => 'Hotspot at tillering stage; pesticide spray applied June 18.',
                'created_at'   => Carbon::create(2025, 6, 15),
                'updated_at'   => Carbon::create(2025, 6, 25),
            ],

            // 2 – Golden Apple Snail – Bangag rice (Marilyn Tumibay)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000003-0000-0000-0000-000000000003',
                'technician_id'=> $techId,
                'pest_name'    => 'Golden Apple Snail',
                'severity'     => 'Medium',
                'date_spotted' => Carbon::create(2025, 6, 20),
                'status'       => 'Active',
                'latitude'     => 16.6831,
                'longitude'    => 121.6696,
                'notes'        => 'Snail damage at transplanting; manual collection ongoing.',
                'created_at'   => Carbon::create(2025, 6, 20),
                'updated_at'   => Carbon::create(2025, 6, 20),
            ],

            // 3 – Fall Armyworm – Garit Norte corn (Renato Caguioa)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000004-0000-0000-0000-000000000004',
                'technician_id'=> $techId,
                'pest_name'    => 'Fall Armyworm',
                'severity'     => 'Critical',
                'date_spotted' => Carbon::create(2025, 5, 10),
                'status'       => 'Resolved',
                'latitude'     => 16.6918,
                'longitude'    => 121.6572,
                'notes'        => 'Widespread infestation at V4 stage; emamectin benzoate applied.',
                'created_at'   => Carbon::create(2025, 5, 10),
                'updated_at'   => Carbon::create(2025, 5, 30),
            ],

            // 4 – Fall Armyworm – Garit Norte 2 (Teresita Palma)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000005-0000-0000-0000-000000000005',
                'technician_id'=> $techId,
                'pest_name'    => 'Fall Armyworm',
                'severity'     => 'High',
                'date_spotted' => Carbon::create(2025, 5, 12),
                'status'       => 'Contained',
                'latitude'     => 16.6895,
                'longitude'    => 121.6551,
                'notes'        => 'Adjacent to critical plot; early detection limited spread.',
                'created_at'   => Carbon::create(2025, 5, 12),
                'updated_at'   => Carbon::create(2025, 5, 22),
            ],

            // 5 – Brown Planthopper – Fugu rice (Artemio Baquiran)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000006-0000-0000-0000-000000000006',
                'technician_id'=> $techId,
                'pest_name'    => 'Brown Planthopper',
                'severity'     => 'High',
                'date_spotted' => Carbon::create(2025, 6, 18),
                'status'       => 'Active',
                'latitude'     => 16.6625,
                'longitude'    => 121.6804,
                'notes'        => 'Hopperburn observed in 30% of field; resistant variety recommended.',
                'created_at'   => Carbon::create(2025, 6, 18),
                'updated_at'   => Carbon::create(2025, 6, 18),
            ],

            // 6 – Tobacco Mosaic Virus – Fugu tobacco (Artemio Baquiran)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000007-0000-0000-0000-000000000007',
                'technician_id'=> $techId,
                'pest_name'    => 'Tobacco Mosaic Virus',
                'severity'     => 'Medium',
                'date_spotted' => Carbon::create(2025, 1, 22),
                'status'       => 'Contained',
                'latitude'     => 16.6641,
                'longitude'    => 121.6821,
                'notes'        => 'Infected plants removed; 15% stand loss.',
                'created_at'   => Carbon::create(2025, 1, 22),
                'updated_at'   => Carbon::create(2025, 2, 5),
            ],

            // 7 – Stem Borer – Lucban rice (Felicito Talosig)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000008-0000-0000-0000-000000000008',
                'technician_id'=> $techId,
                'pest_name'    => 'Yellow Stem Borer',
                'severity'     => 'Medium',
                'date_spotted' => Carbon::create(2025, 7, 1),
                'status'       => 'Active',
                'latitude'     => 16.7134,
                'longitude'    => 121.6768,
                'notes'        => 'Dead-heart symptoms at early vegetative; pheromone traps set.',
                'created_at'   => Carbon::create(2025, 7, 1),
                'updated_at'   => Carbon::create(2025, 7, 1),
            ],

            // 8 – Tobacco Mosaic Virus – Mabbayad tobacco (Rogelio Buenaobra)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000010-0000-0000-0000-000000000010',
                'technician_id'=> $techId,
                'pest_name'    => 'Tobacco Mosaic Virus',
                'severity'     => 'High',
                'date_spotted' => Carbon::create(2025, 2, 5),
                'status'       => 'Resolved',
                'latitude'     => 16.7258,
                'longitude'    => 121.6541,
                'notes'        => 'Removed symptomatic plants; sanitization of tools done.',
                'created_at'   => Carbon::create(2025, 2, 5),
                'updated_at'   => Carbon::create(2025, 2, 28),
            ],

            // 9 – Brown Planthopper – Macarang rice (Danilo Turingan)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000012-0000-0000-0000-000000000012',
                'technician_id'=> $techId,
                'pest_name'    => 'Brown Planthopper',
                'severity'     => 'Critical',
                'date_spotted' => Carbon::create(2025, 6, 28),
                'status'       => 'Active',
                'latitude'     => 16.7308,
                'longitude'    => 121.7022,
                'notes'        => 'Massive outbreak across entire 3-ha field; DA intervention requested.',
                'created_at'   => Carbon::create(2025, 6, 28),
                'updated_at'   => Carbon::create(2025, 6, 28),
            ],

            // 10 – Fall Armyworm – Paddad corn (Jaime Manantan)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000014-0000-0000-0000-000000000014',
                'technician_id'=> $techId,
                'pest_name'    => 'Fall Armyworm',
                'severity'     => 'Medium',
                'date_spotted' => Carbon::create(2025, 5, 20),
                'status'       => 'Resolved',
                'latitude'     => 16.7001,
                'longitude'    => 121.6438,
                'notes'        => 'Early-stage infestation; controlled within 2 weeks.',
                'created_at'   => Carbon::create(2025, 5, 20),
                'updated_at'   => Carbon::create(2025, 6, 2),
            ],

            // 11 – Stem Borer – San Isidro rice (Nimrod Carpio)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000015-0000-0000-0000-000000000015',
                'technician_id'=> $techId,
                'pest_name'    => 'Yellow Stem Borer',
                'severity'     => 'Low',
                'date_spotted' => Carbon::create(2025, 7, 5),
                'status'       => 'Resolved',
                'latitude'     => 16.7319,
                'longitude'    => 121.6475,
                'notes'        => 'Isolated infestation; hand-picked and insecticide spot-treatment.',
                'created_at'   => Carbon::create(2025, 7, 5),
                'updated_at'   => Carbon::create(2025, 7, 15),
            ],

            // 12 – Fall Armyworm – Rang-ayan corn (Elvira Aguinaldo)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000017-0000-0000-0000-000000000017',
                'technician_id'=> $techId,
                'pest_name'    => 'Fall Armyworm',
                'severity'     => 'High',
                'date_spotted' => Carbon::create(2025, 5, 18),
                'status'       => 'Contained',
                'latitude'     => 16.7831,
                'longitude'    => 121.6962,
                'notes'        => 'Remote barangay; delayed response. Biological control used.',
                'created_at'   => Carbon::create(2025, 5, 18),
                'updated_at'   => Carbon::create(2025, 6, 1),
            ],

            // 13 – Fall Armyworm – Naguilian corn (Roberto Cabanatan)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000018-0000-0000-0000-000000000018',
                'technician_id'=> $techId,
                'pest_name'    => 'Fall Armyworm',
                'severity'     => 'Medium',
                'date_spotted' => Carbon::create(2025, 5, 22),
                'status'       => 'Resolved',
                'latitude'     => 16.7758,
                'longitude'    => 121.7072,
                'notes'        => 'Second-season infestation; farmer used recommended dosage.',
                'created_at'   => Carbon::create(2025, 5, 22),
                'updated_at'   => Carbon::create(2025, 6, 5),
            ],

            // 14 – Golden Apple Snail – Gumbauan rice (Priscilla Tallungan)
            [
                'id'           => Str::uuid(),
                'farm_plot_id' => 'b1000020-0000-0000-0000-000000000020',
                'technician_id'=> $techId,
                'pest_name'    => 'Golden Apple Snail',
                'severity'     => 'Low',
                'date_spotted' => Carbon::create(2025, 6, 25),
                'status'       => 'Active',
                'latitude'     => 16.6698,
                'longitude'    => 121.6648,
                'notes'        => 'Small plot; manageable population. Molluscicide applied.',
                'created_at'   => Carbon::create(2025, 6, 25),
                'updated_at'   => Carbon::create(2025, 6, 25),
            ],

        ]);
    }
}
