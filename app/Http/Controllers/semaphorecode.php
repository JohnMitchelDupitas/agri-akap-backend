<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\SmsBroadcast;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BroadcastController extends Controller
{
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
        $query = Farmer::whereNotNull('contact_no');

        if (!empty($validated['target_barangay']) && $validated['target_barangay'] !== 'All') {
            $query->where('barangay', $validated['target_barangay']);
        }

        // Use whereHas to filter by their farm plots' primary crop
        if (!empty($validated['target_commodity']) && $validated['target_commodity'] !== 'All') {
            $query->whereHas('farmPlots', function ($q) use ($validated) {
                $q->where('primary_crop', $validated['target_commodity']);
            });
        }

        // 2. Extract and format phone numbers
        $farmers = $query->get();
        $phoneNumbers = $farmers->pluck('contact_no')->filter()->implode(',');
        $recipientCount = $farmers->count();

        if ($recipientCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No farmers found with valid contact numbers for this target group.'
            ], 400);
        }

        // 3. Send Payload to Semaphore API
        try {
            $response = Http::post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => env('SEMAPHORE_API_KEY', 'dummy_key'),
                'number' => $phoneNumbers, // Comma-separated list for bulk sending
                'message' => $validated['message_body'],
                'sendername' => env('SEMAPHORE_SENDER_NAME', 'MAO-ECHAGUE')
            ]);

            $status = $response->successful() ? 'Success' : 'Failed';

        } catch (\Exception $e) {
            Log::error('Semaphore API Error: ' . $e->getMessage());
            $status = 'Failed (Network Error)';
        }

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
