<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Routed through auth:sanctum middleware globally
    }

 public function rules(): array
    {
        return [
            // Part 1: Personal Information
            'rsbsa_no' => 'nullable|string|max:255|unique:farmers,rsbsa_no',
            'transaction_code' => 'required|string|max:255|unique:farmers,transaction_code',
            'surname' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'ext_name' => 'nullable|string|max:10',
            'no_middle_name' => 'boolean',
            'no_ext_name' => 'boolean',
            'sex' => 'required|in:Male,Female',
            'birthdate' => 'required|date',
            
            // Contact & Ownership
            'mobile_number' => 'required|string|max:15',
            'is_mobile_owner' => 'boolean',
            'mobile_owner_first_name' => 'nullable|string',
            'mobile_owner_middle_name' => 'nullable|string',
            'mobile_owner_surname' => 'nullable|string',
            'mobile_owner_ext_name' => 'nullable|string',

            // Mother's Maiden Name
            'mothers_maiden_first_name' => 'required|string',
            'mothers_maiden_middle_name' => 'nullable|string',
            'mothers_maiden_surname' => 'required|string',
            'mothers_maiden_ext_name' => 'nullable|string',

            // Demographics
            'civil_status' => 'required|string',
            'spouse_first_name' => 'nullable|string',
            'spouse_middle_name' => 'nullable|string',
            'spouse_surname' => 'nullable|string',
            'spouse_ext_name' => 'nullable|string',
            'highest_education' => 'required|string',
            'religion' => 'nullable|string',
            'id_type' => 'nullable|string',
            'id_number' => 'nullable|string',

            // Vulnerability & Associations
            'is_icc_ip' => 'boolean',
            'icc_ip_name' => 'nullable|string',
            'is_pwd' => 'boolean',
            'is_4ps_beneficiary' => 'boolean',
            'association_1' => 'nullable|string',
            'association_2' => 'nullable|string',
            'association_3' => 'nullable|string',

            // Addresses
            'permanent_house_no' => 'nullable|string',
            'permanent_street' => 'nullable|string',
            'permanent_brgy' => 'required|string',
            'permanent_city' => 'required|string',
            'permanent_province' => 'required|string',
            'permanent_region' => 'required|string',

            'provincial_house_no' => 'nullable|string',
            'provincial_street' => 'nullable|string',
            'provincial_brgy' => 'nullable|string',
            'provincial_city' => 'nullable|string',
            'provincial_province' => 'nullable|string',
            'provincial_region' => 'nullable|string',

            // Part 2: Livelihood
            'livelihood_type' => 'required|string',

            // Part 3: Farm Plots Array Validation
            'plots' => 'required|array|min:1',
            'plots.*.location_brgy' => 'required|string',
            'plots.*.location_city' => 'required|string',
            'plots.*.location_province' => 'required|string',
            
            // Allow nulls for numeric values to prevent DB crashes from empty strings ""
            'plots.*.latitude' => 'nullable|numeric',
            'plots.*.longitude' => 'nullable|numeric',
            'plots.*.total_parcel_area_ha' => 'required|numeric',
            'plots.*.is_ancestral_domain' => 'boolean',
            'plots.*.is_agrarian_reform_beneficiary' => 'boolean',
            'plots.*.ownership_type' => 'required|string',
            'plots.*.land_owner_first_name' => 'nullable|string',
            'plots.*.land_owner_surname' => 'nullable|string',
            'plots.*.land_owner_ext_name' => 'nullable|string',
            'plots.*.proof_of_ownership_document' => 'required|string',
            'plots.*.commodity' => 'required|string',
            'plots.*.size_ha' => 'required|numeric',
            'plots.*.no_of_heads_or_trees' => 'nullable|integer',
            'plots.*.farm_type' => 'required|string',
            'plots.*.is_organic' => 'boolean',
            'plots.*.cropping_schedule' => 'nullable|string',
            'plots.*.rotational_tiller_full_name' => 'nullable|string',
            'plots.*.remarks' => 'nullable|string',
        ];
    }
}