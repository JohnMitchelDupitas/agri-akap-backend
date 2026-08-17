<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — foreign-key dependents must run after their parents.
     * 1. Barangays (64 Echague rows + precise coordinate overlay)
     * 2. Users (technician + admin + demo barangay_official)
     * 3. BarangayUserSeeder (64 encoder accounts, one per Echague barangay)
     * 4. Programs (subsidy programs — referenced by Distributions)
     * 5. Farmers (RSBSA registrations for Echague, Isabela)
     * 6. FarmPlots (geo-tagged plots linked to farmers)
     * 7. DamageAssessments (linked to plots + users)
     * 8. PestOutbreaks (linked to plots + technician)
     * 9. CropMonitorings (linked to plots + technician; multi-year yield history)
     */
    public function run(): void
    {
        $this->call([
            BarangaySeeder::class,
            BarangayCoordinateSeeder::class,
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
