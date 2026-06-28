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
        'program_id',
        'farmer_id',
        'distributed_by',
        'quantity_claimed',
        'status',
        'device_id',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

   // Connects to the Program
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    // Connects to the Farmer
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    // Connects to the Technician (User) who dispensed it
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }
}
