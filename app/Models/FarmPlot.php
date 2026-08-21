<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FarmPlot extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'farmer_id', 'parcel_name',
        'location_brgy', 'location_city', 'location_province',
        'latitude', 'longitude', 'georef_id', 'total_parcel_area_ha', 'is_ancestral_domain',
        'is_agrarian_reform_beneficiary', 'ownership_type', 'land_owner_first_name',
        'land_owner_surname', 'land_owner_ext_name', 'landowner_name', 'land_owner_rsbsa_no',
        'proof_of_ownership_document',
        'commodity', 'planting_start_month', 'planting_end_month',
        'size_ha', 'no_of_heads_or_trees', 'farm_type', 'is_organic',
        'cropping_schedule', 'rotational_tiller_full_name', 'remarks',
        'boundary_points', 'non_productive_area_sqm', 'has_discrepancy', 'georef_sms_sent_at',
    ];

    protected $casts = [
        'is_ancestral_domain' => 'boolean',
        'is_agrarian_reform_beneficiary' => 'boolean',
        'is_organic' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'total_parcel_area_ha' => 'decimal:4',
        'size_ha' => 'decimal:4',
        'boundary_points' => 'array',
        'non_productive_area_sqm' => 'decimal:2',
        'has_discrepancy' => 'boolean',
        'georef_sms_sent_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
