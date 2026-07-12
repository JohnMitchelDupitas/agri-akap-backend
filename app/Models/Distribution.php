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
        'id', // allow client-generated UUID for offline-first idempotency
        'program_id',
        'farmer_id',
        'distributed_by',
        'quantity_claimed',
        'item_released',
        'geo_tag_lat',
        'geo_tag_long',
        'photo_proof_path',
        'status',
        'device_id',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'geo_tag_lat' => 'decimal:8',
        'geo_tag_long' => 'decimal:8',
    ];

    /**
     * Model-level safeguard against double-dipping: a farmer may claim a given
     * program only once. Backstops the DB unique index and the controller check
     * without disturbing offline client_id idempotency (same-id re-inserts are
     * caught earlier in executeClaim()).
     */
    protected static function booted(): void
    {
        static::creating(function (Distribution $distribution) {
            $duplicate = static::where('farmer_id', $distribution->farmer_id)
                ->where('program_id', $distribution->program_id)
                ->when($distribution->id, fn ($q) => $q->where('id', '!=', $distribution->id))
                ->exists();

            if ($duplicate) {
                throw new \RuntimeException('This farmer has already claimed their subsidy for this program.');
            }
        });
    }

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
