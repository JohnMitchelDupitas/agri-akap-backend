<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcicEnrollment extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'farmer_id',
        'farm_plot_id',
        'crop_season',
        'coverage_year',
        'commodity',
        'insured_area_ha',
        'policy_reference',
        'enrolled_by',
        'enrolled_at',
        'status',
        'remarks',
    ];

    protected $casts = [
        'coverage_year' => 'integer',
        'insured_area_ha' => 'decimal:4',
        'enrolled_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function farmPlot(): BelongsTo
    {
        return $this->belongsTo(FarmPlot::class, 'farm_plot_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
