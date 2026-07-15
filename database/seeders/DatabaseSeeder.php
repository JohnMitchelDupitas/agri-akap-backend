<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — foreign-key dependents must run after their parents.
     * 1. Users (technician + admin + barangay_official accounts)
     * 2. Programs (subsidy programs — referenced by Distributions)
     * 3. Farmers (RSBSA registrations for Echague, Isabela)
     * 4. FarmPlots (geo-tagged plots linked to farmers)
     * 5. DamageAssessments (linked to plots + users)
     * 6. PestOutbreaks (linked to plots + technician)
     * 7. CropMonitorings (linked to plots + technician; multi-year yield history)
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProgramSeeder::class,
            FarmerSeeder::class,
            FarmPlotSeeder::class,
            DamageAssessmentSeeder::class,
            PcicEnrollmentSeeder::class,
            PestOutbreakSeeder::class,
            CropMonitoringSeeder::class,
            ReportWorkflowSeeder::class,
        ]);
    }
}
