<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logged farmer refusal to consent to DA-RSBSA georeferencing. Part of the
 * "3-Attempt Rule": once a farmer accumulates 3 refusal records, MAO staff
 * may apply the RSBSA exclusion protocol.
 */
class GeoTagRefusal extends Model
{
    use HasUuid;

    protected $fillable = [
        'client_id', 'farmer_id', 'rsbsa_no', 'technician_id', 'device_id',
        'attempt_number', 'reason',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
