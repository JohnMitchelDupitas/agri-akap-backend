<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsBroadcast extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'target_barangay',
        'target_commodity', 
        'message_body',
        'recipient_count',
        'status'
    ];
}
