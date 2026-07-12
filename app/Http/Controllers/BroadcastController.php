<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\SmsBroadcast;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BroadcastController extends Controller
{
    public function __construct(private SmsService $sms)
    {
    }

    /**
     * Get recent SMS campaigns for the dashboard.
     */
    public function index(): JsonResponse
    {
        $broadcasts = SmsBroadcast::orderBy('created_at', 'desc')->take(10)->get();
        return response()->json(['status' => 'success', 'data' => $broadcasts], 200);
    }

    /**
     * Send a bulk SMS to filtered farmers via Semaphore API.
     */
    public function sendBulkSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_body' => 'required|string|max:160', // Standard SMS limit
            'target_barangay' => 'nullable|string',
            'target_commodity' => 'nullable|string',
        ]);

        // 1. Build the query to find target farmers
        $query = Farmer::whereNotNull('mobile_number');

        if (!empty($validated['target_barangay']) && $validated['target_barangay'] !== 'All') {
            $query->where('permanent_brgy', $validated['target_barangay']);
        }

        // Use whereHas to filter by their farm plots' commodity
        if (!empty($validated['target_commodity']) && $validated['target_commodity'] !== 'All') {
            $query->whereHas('farmPlots', function ($q) use ($validated) {
                $q->where('commodity', $validated['target_commodity']);
            });
        }

        // 2. Extract and format phone numbers
        $farmers = $query->get();
        $phoneNumbers = $farmers->pluck('mobile_number')->filter()->values()->all();
        $recipientCount = count($phoneNumbers);

        if ($recipientCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No farmers found with valid contact numbers for this target group.'
            ], 400);
        }

        // 3. Dispatch through the configured SMS gateway (IPROG / Semaphore).
        $result = $this->sms->sendBulk($phoneNumbers, $validated['message_body']);
        $status = $result['success']
            ? 'Success (' . $result['provider'] . ')'
            : 'Failed (' . $result['provider'] . ')';

        // 4. Log the Campaign in our database
        $broadcast = SmsBroadcast::create([
            'target_barangay' => $validated['target_barangay'] ?? 'All',
            'target_commodity' => $validated['target_commodity'] ?? 'All',
            'message_body' => $validated['message_body'],
            'recipient_count' => $recipientCount,
            'status' => $status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Broadcast processed. Queued to $recipientCount farmers.",
            'data' => $broadcast
        ], 200);
    }
}
