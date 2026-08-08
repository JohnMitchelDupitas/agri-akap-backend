<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SMS campaign ledger (sms_broadcasts).
 * Fields align with the AGRI-AKAP broadcast log:
 * message_body (message_content), recipient_count, trigger_type, status.
 */
class SmsBroadcast extends Model
{
    use HasFactory, HasUuid;

    public const TRIGGER_MANUAL = 'Manual';

    public const TRIGGER_AUTOMATED_WEATHER = 'Automated_Weather';

    public const STATUS_SENT = 'Sent';

    public const STATUS_FAILED = 'Failed';

    protected $table = 'sms_broadcasts';

    protected $fillable = [
        'target_barangay',
        'target_commodity',
        'message_body',
        'trigger_type',
        'alert_type',
        'recipient_count',
        'status',
    ];

    protected $casts = [
        'recipient_count' => 'integer',
    ];

    protected $appends = [
        'message_content',
    ];

    public function getMessageContentAttribute(): ?string
    {
        return $this->message_body;
    }
}
