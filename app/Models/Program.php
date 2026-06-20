<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'budget_allocation',
        'funding_source',
        'total_quantity',
        'remaining_quantity',
        'unit_of_measurement',
        'per_hectare_allocation',
        'max_hectare_cap',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'budget_allocation' => 'decimal:2',
        'per_hectare_allocation' => 'decimal:2',
        'max_hectare_cap' => 'decimal:2',
        'total_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
