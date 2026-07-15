<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportWorkflow extends Model
{
    use HasFactory, HasUuid;

    public const TYPES = [
        'Provincial Accomplishment Report',
        'Palay Situation Report',
        'Corn Situation Report',
    ];

    protected $fillable = [
        'report_type',
        'raw_data_collector_id',
        'consolidator_id',
        'provincial_status',
        'file_url',
        'submission_date',
        'report_parameters',
        'payload_snapshot',
        'verified_at',
        'finalized_at',
    ];

    protected $casts = [
        'report_parameters' => 'array',
        'payload_snapshot' => 'array',
        'submission_date' => 'date',
        'verified_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raw_data_collector_id');
    }

    public function consolidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consolidator_id');
    }
}
