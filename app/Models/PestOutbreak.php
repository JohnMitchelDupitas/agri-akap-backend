<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PestOutbreak extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'farm_plot_id',
        'technician_id',
        'pest_name',
        'severity',
        'date_spotted',
        'status',
        'recommended_intervention',
        'latitude',
        'longitude',
        'notes',
    ];

    protected $casts = [
        'date_spotted' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class, 'farm_plot_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
