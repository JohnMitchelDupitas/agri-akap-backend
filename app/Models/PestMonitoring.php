<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PestMonitoring extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'pest_monitoring';

    protected $fillable = [
        'id',
        'client_id',
        'farmer_id',
        'technician_id',
        'crop',
        'pest_name',
        'incidence',
        'severity',
        'advisory',
        'is_outbreak',
        'photo_path',
        'latitude',
        'longitude',
        'report_ref',
        'item_distributed',
        'quantity',
        'device_id',
    ];

    protected $casts = [
        'is_outbreak' => 'boolean',
        'incidence' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
