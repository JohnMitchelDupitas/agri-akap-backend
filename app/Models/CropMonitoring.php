<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropMonitoring extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'farm_plot_id',
        'technician_id',
        'crop_planted',
        'season',
        'area_planted_ha',
        'expected_yield_kg',
        'actual_yield_kg',
        'crop_stage',
        'year',
        'soil_ph'
    ];

    protected $casts = [
        'area_planted_ha' => 'decimal:2',
        'expected_yield_kg' => 'decimal:2',
        'actual_yield_kg' => 'decimal:2',
        'soil_ph' => 'decimal:2',
        'year' => 'integer',
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
