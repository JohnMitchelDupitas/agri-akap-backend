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
        'farmer_id', 'location_brgy', 'location_city', 'location_province',
        'latitude', 'longitude', 'total_parcel_area_ha', 'is_ancestral_domain',
        'is_agrarian_reform_beneficiary', 'ownership_type', 'land_owner_first_name',
        'land_owner_surname', 'land_owner_ext_name', 'proof_of_ownership_document',
        'commodity', 'size_ha', 'no_of_heads_or_trees', 'farm_type', 'is_organic',
        'cropping_schedule', 'rotational_tiller_full_name', 'remarks'
    ];

    protected $casts = [
        'is_ancestral_domain' => 'boolean',
        'is_agrarian_reform_beneficiary' => 'boolean',
        'is_organic' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'total_parcel_area_ha' => 'decimal:4',
        'size_ha' => 'decimal:4',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
