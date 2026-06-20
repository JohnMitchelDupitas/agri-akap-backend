<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced via Sanctum middleware guards
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:programs,name',
            'description' => 'nullable|string',
            'type' => 'required|in:seeds,fertilizer,cash,equipment',
            'budget_allocation' => 'required|numeric|min:0',
            'funding_source' => 'required|string|max:255',
            'total_quantity' => 'required|integer|min:1',
            'unit_of_measurement' => 'required|string|max:50',
            'per_hectare_allocation' => 'required|numeric|min:0.01',
            'max_hectare_cap' => 'required|numeric|min:0.01',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ];
    }
}
