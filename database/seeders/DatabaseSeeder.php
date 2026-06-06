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
            // Later we will add:
            // ProgramSeeder::class,
            // FarmerSeeder::class,
        ]);
    }
}
