<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Server-side record of a Mobile GIS geo-tag (farm boundary polygon or
 * incident pin) synced from the technician's offline queue. Doubles as the
 * digital proof-of-measurement trail for the DA-RSBSA Georeferencing
 * Guidelines (RCM Protocol).
 */
class GeoTag extends Model
{
    use HasUuid;

    protected $fillable = [
        'client_id', 'farmer_id', 'rsbsa_no', 'technician_id', 'device_id',
        'geometry_type', 'coordinates', 'crop_planted', 'crop_variety',
        'planting_start_month', 'planting_end_month',
        'incident_type', 'observations', 'photo_path', 'accuracy_m',
        'farmer_signature_path', 'aew_signature_path',
        'gross_area_sqm', 'non_productive_area_sqm', 'final_area_sqm', 'final_area_ha',
        'has_discrepancy', 'farm_plot_id', 'sms_sent_at',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'has_discrepancy' => 'boolean',
        'accuracy_m' => 'decimal:2',
        'gross_area_sqm' => 'decimal:2',
        'non_productive_area_sqm' => 'decimal:2',
        'final_area_sqm' => 'decimal:2',
        'final_area_ha' => 'decimal:4',
        'sms_sent_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class);
    }
}
