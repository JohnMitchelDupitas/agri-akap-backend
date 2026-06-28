<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Fetch high-level statistics and the recent audit trail.
     */
    public function getStats(): JsonResponse
    {
        // 1. Calculate Active Programs
        $activeProgramsCount = Program::where('is_active', true)
            ->where('end_date', '>=', now())
            ->count();

        // 2. Calculate Total Registered Farmers
        $totalFarmers = Farmer::count();

        // 3. Aggregate Total Dispensed Subsidies (Grouped by Unit)
        // e.g., Output: [{ unit: 'bags', total: 1500 }, { unit: 'pieces', total: 450 }]
        $dispensedTotals = Distribution::join('programs', 'distributions.program_id', '=', 'programs.id')
            ->select('programs.unit_of_measurement as unit', DB::raw('SUM(distributions.quantity_claimed) as total_dispensed'))
            ->where('distributions.status', 'completed')
            ->groupBy('programs.unit_of_measurement')
            ->get();

        // 4. Fetch the Audit Trail (Latest 10 Transactions)
        // Eager load specific columns to keep the JSON payload lightweight and fast
        $recentTransactions = Distribution::with([
            'farmer:id,first_name,surname',
            'program:id,name,unit_of_measurement',
            'technician:id,name'
        ])
        ->latest()
        ->take(10)
        ->get()
        ->map(function ($transaction) {
            // Flatten the response for the frontend table
            return [
                'id' => $transaction->id,
                'date' => $transaction->created_at->format('M d, Y h:i A'),
                'farmer_name' => $transaction->farmer->first_name . ' ' . $transaction->farmer->surname,
                'program_name' => $transaction->program->name,
                'dispensed' => $transaction->quantity_claimed . ' ' . $transaction->program->unit_of_measurement,
                'technician' => $transaction->technician->name,
            ];
        });

        // 5. Return the compiled Mission Control payload
        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'active_programs' => $activeProgramsCount,
                    'total_farmers' => $totalFarmers,
                    'dispensed_breakdown' => $dispensedTotals
                ],
                'audit_trail' => $recentTransactions
            ]
        ], 200);
    }
}
