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
    public function index(): JsonResponse
    {
        $broadcasts = SmsBroadcast::orderBy('created_at', 'desc')->take(10)->get();
        return response()->json(['status' => 'success', 'data' => $broadcasts], 200);
    }

    public function sendBulkSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_body' => 'required|string|max:160',
            'target_barangay' => 'nullable|string',
            'target_commodity' => 'nullable|string',
        ]);

        // 1. Build the query to find target farmers
        $query = Farmer::whereNotNull('mobile_number');

        if (!empty($validated['target_barangay']) && $validated['target_barangay'] !== 'All') {
            $query->where('barangay', $validated['target_barangay']);
        }

        if (!empty($validated['target_commodity']) && $validated['target_commodity'] !== 'All') {
            $query->whereHas('farmPlots', function ($q) use ($validated) {
                $q->where('primary_crop', $validated['target_commodity']);
            });
        }

        // 2. Extract phone numbers
        $farmers = $query->get();
        $phoneNumbers = $farmers->pluck('mobile_number')->filter()->implode(',');
        $recipientCount = $farmers->count();

        if ($recipientCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No farmers found with valid contact numbers for this target group.'
            ], 400);
        }

        // 3. Send Payload to IPROG SMS API (Bulk Endpoint)
        try {
            // 👈 FIXED: Appended /send_bulk to the API URL
            $response = Http::post('https://www.iprogsms.com/api/v1/sms_messages/send_bulk', [
                'api_token'    => env('IPROG_API_TOKEN'),
                'phone_number' => $phoneNumbers,
                'message'      => $validated['message_body']
            ]);

            $status = $response->successful() ? 'Success' : 'Failed';

            if ($response->failed()) {
                Log::error('IPROG API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('IPROG API Network Error: ' . $e->getMessage());
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
            'message' => "Broadcast processed. Queued to $recipientCount farmers via IPROG.",
            'data' => $broadcast
        ], 200);
    }
}
