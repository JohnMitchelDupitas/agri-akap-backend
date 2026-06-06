<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    use HasFactory, HasUuid; // No soft deletes here to keep unique constraint logic simple

    protected $fillable = [
        'farmer_id',
        'program_id',
        'distributed_by',
        'status',
        'device_id',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }
}
