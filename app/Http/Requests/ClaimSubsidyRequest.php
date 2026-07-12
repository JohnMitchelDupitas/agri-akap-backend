<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimSubsidyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via Sanctum middleware
    }

    public function rules(): array
    {
        return [
            // The UUID from the farmer's scanned QR Code
            'farmer_id' => 'required|uuid|exists:farmers,id',
            // The UUID of the active program the technician selected
            'program_id' => 'required|uuid|exists:programs,id',
            // Offline-first metadata (optional)
            'client_id' => 'nullable|uuid', // client-generated Distribution UUID
            'device_id' => 'nullable|string|max:255',
            'claimed_at' => 'nullable|date',
        ];
    }
}
