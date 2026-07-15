<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Sample statutory report workflow records for Echague MAO demo.
 */
class ReportWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@mao.com')->value('id');
        if (!$adminId) {
            return;
        }

        $now = Carbon::now();
        $params = [
            'date_from' => $now->copy()->startOfYear()->format('Y-m-d'),
            'date_to' => $now->format('Y-m-d'),
            'barangay' => null,
            'commodity' => null,
        ];

        $basePayload = [
            'generated_at' => $now->format('F j, Y g:i A'),
            'report_type' => 'Provincial Accomplishment Report',
            'period_label' => $now->format('Y'),
            'filters_applied' => $params,
            'totals' => [
                'farmers' => DB::table('farmers')->count(),
                'programs' => DB::table('programs')->count(),
                'distributions' => DB::table('distributions')->count(),
                'approved_damage_claims' => DB::table('damage_assessments')->where('status', 'Approved')->count(),
                'total_value_lost' => 0,
            ],
            'program_performance_matrix' => [],
            'programs' => [],
            'damage_assessments' => [],
            'pest_summary_by_barangay' => [],
            'farmers_by_barangay' => [],
        ];

        DB::table('report_workflows')->insert([
            [
                'id' => Str::uuid()->toString(),
                'report_type' => 'Provincial Accomplishment Report',
                'raw_data_collector_id' => $adminId,
                'consolidator_id' => null,
                'provincial_status' => 'Pending',
                'file_url' => null,
                'submission_date' => null,
                'report_parameters' => json_encode($params),
                'payload_snapshot' => json_encode($basePayload),
                'verified_at' => null,
                'finalized_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'report_type' => 'Palay Situation Report',
                'raw_data_collector_id' => $adminId,
                'consolidator_id' => $adminId,
                'provincial_status' => 'Verified',
                'file_url' => null,
                'submission_date' => null,
                'report_parameters' => json_encode(array_merge($params, ['commodity' => 'Rice'])),
                'payload_snapshot' => json_encode(array_merge($basePayload, [
                    'report_type' => 'Palay Situation Report',
                    'filters_applied' => array_merge($params, ['commodity' => 'Rice']),
                ])),
                'verified_at' => $now->copy()->subDays(2),
                'finalized_at' => null,
                'created_at' => $now->copy()->subDays(3),
                'updated_at' => $now->copy()->subDays(2),
            ],
            [
                'id' => Str::uuid()->toString(),
                'report_type' => 'Corn Situation Report',
                'raw_data_collector_id' => $adminId,
                'consolidator_id' => $adminId,
                'provincial_status' => 'Finalized',
                'file_url' => 'storage/reports/demo-finalized.json',
                'submission_date' => $now->copy()->subDays(5)->format('Y-m-d'),
                'report_parameters' => json_encode(array_merge($params, ['commodity' => 'Corn'])),
                'payload_snapshot' => json_encode(array_merge($basePayload, [
                    'report_type' => 'Corn Situation Report',
                    'filters_applied' => array_merge($params, ['commodity' => 'Corn']),
                ])),
                'verified_at' => $now->copy()->subDays(6),
                'finalized_at' => $now->copy()->subDays(5),
                'created_at' => $now->copy()->subDays(7),
                'updated_at' => $now->copy()->subDays(5),
            ],
        ]);
    }
}
