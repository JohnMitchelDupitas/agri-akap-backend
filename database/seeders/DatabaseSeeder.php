<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — foreign-key dependents must run after their parents.
     * 1. Users (technician + admin + demo barangay_official)
     * 2. BarangayUserSeeder (64 encoder accounts, one per Echague barangay)
     * 3. Programs (subsidy programs — referenced by Distributions)
     * 4. Farmers (RSBSA registrations for Echague, Isabela)
     * 5. FarmPlots (geo-tagged plots linked to farmers)
     * 6. DamageAssessments (linked to plots + users)
     * 7. PestOutbreaks (linked to plots + technician)
     * 8. CropMonitorings (linked to plots + technician; multi-year yield history)
     */
    public function run(): void
    {
        $this->call([
            BarangaySeeder::class,
            UserSeeder::class,
            BarangayUserSeeder::class,
            ProgramSeeder::class,
            FarmerSeeder::class,
            FarmPlotSeeder::class,
            DamageAssessmentSeeder::class,
            PestOutbreakSeeder::class,
            CropMonitoringSeeder::class,
            ReportWorkflowSeeder::class,
        ]);
    }
}
