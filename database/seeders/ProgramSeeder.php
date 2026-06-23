<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProgramSeeder extends Seeder
{
    /**
     * Seed the programs table.
     */
    public function run(): void
    {
        // 👈 1. Turn off the safety lock
        Schema::disableForeignKeyConstraints();

        DB::table('programs')->truncate();

        // 👈 2. Turn the safety lock back on
        Schema::enableForeignKeyConstraints();

        DB::table('programs')->insert([
            [
                'id' => Str::uuid(),
                'name' => '2026 Wet Season Hybrid Rice Seed Distribution',
                'description' => 'Distribution of certified hybrid rice seeds for the 2026 wet season crop production. Targets smallholder farmers in priority municipalities.',
                'type' => 'seeds',
                'budget_allocation' => 2500000.00,
                'funding_source' => 'DA-RFO II',
                'total_quantity' => 50000,
                'remaining_quantity' => 50000,
                'unit_of_measurement' => 'bags',
                'per_hectare_allocation' => 2.50,
                'max_hectare_cap' => 3.00,
                'start_date' => Carbon::create(2026, 5, 1),
                'end_date' => Carbon::create(2026, 9, 30),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Fertilizer Subsidy Program - Dry Season 2026',
                'description' => 'Subsidized fertilizer distribution for dry season farming. Includes complete fertilizer, urea, and phosphate products.',
                'type' => 'fertilizer',
                'budget_allocation' => 1800000.00,
                'funding_source' => 'DA-RFO II',
                'total_quantity' => 30000,
                'remaining_quantity' => 30000,
                'unit_of_measurement' => 'bags',
                'per_hectare_allocation' => 3.00,
                'max_hectare_cap' => 2.50,
                'start_date' => Carbon::create(2026, 11, 1),
                'end_date' => Carbon::create(2027, 3, 31),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Agricultural Equipment Assistance Program',
                'description' => 'Subsidized agricultural equipment for mechanization support. Includes hand tractors, threshers, and other farm implements.',
                'type' => 'equipment',
                'budget_allocation' => 5000000.00,
                'funding_source' => 'DA-RFO II',
                'total_quantity' => 500,
                'remaining_quantity' => 500,
                'unit_of_measurement' => 'pieces',
                'per_hectare_allocation' => 0.10,
                'max_hectare_cap' => 5.00,
                'start_date' => Carbon::create(2026, 1, 15),
                'end_date' => Carbon::create(2026, 12, 31),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Cash Assistance for Affected Farmers',
                'description' => 'Emergency cash assistance program for farmers affected by natural disasters and calamities.',
                'type' => 'cash',
                'budget_allocation' => 3000000.00,
                'funding_source' => 'DA-RFO II',
                'total_quantity' => 3000,
                'remaining_quantity' => 2500,
                'unit_of_measurement' => 'pesos',
                'per_hectare_allocation' => 5000.00,
                'max_hectare_cap' => 2.00,
                'start_date' => Carbon::create(2026, 3, 1),
                'end_date' => Carbon::create(2026, 8, 31),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Organic Farming Support Program',
                'description' => 'Support program for organic certification and organic inputs including bio-fertilizers and bio-pesticides.',
                'type' => 'seeds',
                'budget_allocation' => 1200000.00,
                'funding_source' => 'DA-RFO II',
                'total_quantity' => 15000,
                'remaining_quantity' => 8000,
                'unit_of_measurement' => 'liters',
                'per_hectare_allocation' => 50.00,
                'max_hectare_cap' => 2.00,
                'start_date' => Carbon::create(2026, 2, 1),
                'end_date' => Carbon::create(2026, 10, 31),
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => now(),
            ],
        ]);
    }
}
