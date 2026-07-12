<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Part 1: Personal Information ──────────────────────────────
            'rsbsa_no' => 'nullable|string|max:255|unique:farmers,rsbsa_no',
            'transaction_code' => 'required|string|max:255|unique:farmers,transaction_code',
            'photo_base64' => 'nullable|string', // optional; decoded server-side

            'surname' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'ext_name' => 'nullable|string|max:10',
            'no_middle_name' => 'boolean',
            'no_ext_name' => 'boolean',
            'sex' => 'required|in:Male,Female',
            'birthdate' => 'required|date',
            'place_of_birth_city' => 'nullable|string|max:100',
            'place_of_birth_province' => 'nullable|string|max:100',

            // ── Contact & Mobile Owner ─────────────────────────────────────
            'mobile_number' => 'required|string|max:15',
            'is_mobile_owner' => 'boolean',
            'mobile_owner_first_name' => 'nullable|string|max:100',
            'mobile_owner_middle_name' => 'nullable|string|max:100',
            'mobile_owner_surname' => 'nullable|string|max:100',
            'mobile_owner_ext_name' => 'nullable|string|max:10',

            // ── Mother's Maiden Name ───────────────────────────────────────
            'mothers_maiden_first_name' => 'required|string|max:100',
            'mothers_maiden_middle_name' => 'nullable|string|max:100',
            'mothers_maiden_surname' => 'required|string|max:100',
            'mothers_maiden_ext_name' => 'nullable|string|max:10',

            // ── Demographics ───────────────────────────────────────────────
            'civil_status' => 'required|in:Single,Married,Widow/er,Legally Separated',
            'spouse_first_name' => 'nullable|string|max:100',
            'spouse_middle_name' => 'nullable|string|max:100',
            'spouse_surname' => 'nullable|string|max:100',
            'spouse_ext_name' => 'nullable|string|max:10',
            'highest_education' => 'required|in:Pre-school,Elementary,High School non K-12,Junior High School K-12,Senior High School K-12,College,Vocational,Post-graduate,None',
            'religion' => 'nullable|string|max:100',
            'id_type' => 'nullable|string|max:100',
            'id_number' => 'nullable|string|max:100',

            // ── Vulnerability & Associations ───────────────────────────────
            'is_icc_ip' => 'boolean',
            'icc_ip_name' => 'nullable|string|max:255',
            'is_pwd' => 'boolean',
            'is_4ps_beneficiary' => 'boolean',
            'association_1' => 'nullable|string|max:255',
            'association_2' => 'nullable|string|max:255',
            'association_3' => 'nullable|string|max:255',

            // ── Addresses ──────────────────────────────────────────────────
            // house_no / street are nullable at DB level (farmers may not have a
            // formal house number in rural barangays).
            'permanent_house_no' => 'nullable|string|max:50',
            'permanent_street' => 'nullable|string|max:100',
            'permanent_brgy' => 'required|string|max:100',
            'permanent_city' => 'required|string|max:100',
            'permanent_province' => 'required|string|max:100',
            'permanent_region' => 'required|string|max:100',

            'provincial_house_no' => 'nullable|string|max:50',
            'provincial_street' => 'nullable|string|max:100',
            'provincial_brgy' => 'nullable|string|max:100',
            'provincial_city' => 'nullable|string|max:100',
            'provincial_province' => 'nullable|string|max:100',
            'provincial_region' => 'nullable|string|max:100',

            // ── Part 2: Livelihood ─────────────────────────────────────────
            'livelihood_type' => 'required|in:Farmer,Farm Worker,Fisher,Agri-Youth',
            'livelihood_detail' => 'nullable|string|max:100',

            // ── Part 3: Farm Plots (at least one required) ─────────────────
            'plots' => 'required|array|min:1',
            'plots.*.location_brgy' => 'required|string|max:100',
            'plots.*.location_city' => 'required|string|max:100',
            'plots.*.location_province' => 'required|string|max:100',
            'plots.*.latitude' => 'nullable|numeric|between:-90,90',
            'plots.*.longitude' => 'nullable|numeric|between:-180,180',
            'plots.*.georef_id' => 'nullable|string|max:100',
            'plots.*.total_parcel_area_ha' => 'required|numeric|min:0.01',
            'plots.*.is_ancestral_domain' => 'boolean',
            'plots.*.is_agrarian_reform_beneficiary' => 'boolean',
            // Official DA tenurial statuses.
            'plots.*.ownership_type' => 'required|in:Registered Owner,Tenant,Lessee,Others',
            // Landowner name + RSBSA no are required when the farmer is a Tenant.
            'plots.*.land_owner_first_name' => 'required_if:plots.*.ownership_type,Tenant|nullable|string|max:100',
            'plots.*.land_owner_surname' => 'required_if:plots.*.ownership_type,Tenant|nullable|string|max:100',
            'plots.*.land_owner_ext_name' => 'nullable|string|max:10',
            'plots.*.land_owner_rsbsa_no' => 'required_if:plots.*.ownership_type,Tenant|nullable|string|max:100',
            'plots.*.proof_of_ownership_document' => 'required|string|max:100',
            'plots.*.commodity' => 'required|string|max:100',
            'plots.*.size_ha' => 'required|numeric|min:0.01',
            'plots.*.no_of_heads_or_trees' => 'nullable|integer|min:0',
            // Official DA farm-type classifications.
            'plots.*.farm_type' => 'required|in:Irrigated,Rainfed Upland,Rainfed Lowland,Urban/Peri-Urban',
            'plots.*.is_organic' => 'boolean',
            'plots.*.cropping_schedule' => 'nullable|string|max:100',
            'plots.*.rotational_tiller_full_name' => 'nullable|string|max:255',
            'plots.*.remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'plots.required' => 'At least one farm plot must be registered.',
            'plots.min' => 'At least one farm plot must be registered.',
            'plots.*.commodity.required' => 'Each plot must have a commodity specified.',
            'plots.*.size_ha.required' => 'Each plot must have a farm size in hectares.',
            'plots.*.ownership_type.in' => 'Select a valid tenurial status (Registered Owner, Tenant, Lessee, Others).',
            'plots.*.farm_type.in' => 'Select a valid farm type (Irrigated, Rainfed Upland, Rainfed Lowland, Urban/Peri-Urban).',
            'plots.*.land_owner_first_name.required_if' => 'Landowner first name is required for tenant-tilled parcels.',
            'plots.*.land_owner_surname.required_if' => 'Landowner surname is required for tenant-tilled parcels.',
            'plots.*.land_owner_rsbsa_no.required_if' => 'Landowner RSBSA number is required for tenant-tilled parcels.',
            'civil_status.in' => 'Select a valid civil status.',
            'highest_education.in' => 'Select a valid education level.',
            'livelihood_type.in' => 'Select a valid livelihood type.',
        ];
    }
}
