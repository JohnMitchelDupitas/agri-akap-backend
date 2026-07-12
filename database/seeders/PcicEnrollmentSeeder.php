<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Pre-calamity PCIC crop insurance enrollments for Echague farmers.
 */
class PcicEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@mao.com')->value('id');

        $plots = DB::table('farm_plots')
            ->select('id', 'farmer_id', 'commodity', 'size_ha')
            ->limit(8)
            ->get();

        if ($plots->isEmpty()) {
            return;
        }

        $rows = [];
        $now = Carbon::now();
        $year = (int) $now->format('Y');

        foreach ($plots as $i => $plot) {
            $rows[] = [
                'id' => Str::uuid(),
                'farmer_id' => $plot->farmer_id,
                'farm_plot_id' => $plot->id,
                'crop_season' => $i % 2 === 0 ? "{$year} Wet Season" : "{$year} Dry Season",
                'coverage_year' => $year,
                'commodity' => $plot->commodity,
                'insured_area_ha' => $plot->size_ha,
                'policy_reference' => $i < 3 ? 'PCIC-ECH-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT) : null,
                'enrolled_by' => $adminId,
                'enrolled_at' => $now->copy()->subDays(30 - $i),
                'status' => $i < 5 ? 'Active' : ($i < 7 ? 'Submitted' : 'Active'),
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('pcic_enrollments')->insert($rows);
    }
}
