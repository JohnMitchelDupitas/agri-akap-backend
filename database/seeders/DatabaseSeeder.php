<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Register all your individual seeders here
        $this->call([
            UserSeeder::class,
            ProgramSeeder::class,
            FarmerSeeder::class,
            FarmPlotSeeder::class,
        ]);
    }
}
