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
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'budget_allocation' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get all distributions under this program.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
