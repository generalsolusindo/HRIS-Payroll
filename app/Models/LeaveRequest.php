<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $request_number
 * @property int $user_id
 * @property string $type
 * @property Carbon $leave_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $estimated_arrival
 * @property string|null $duration
 * @property string|null $reason
 * @property bool $emergency
 * @property string|null $emergency_reason
 * @property bool|null $contactable
 * @property string $status
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 * @property string|null $approval_note
 * @property string|null $rejection_reason
 * @property int|null $hrd_processed_by
 * @property Carbon|null $hrd_processed_at
 * @property string|null $hrd_decision
 * @property string|null $hrd_note
 * @property string $email_status
 * @property string $telegram_status
 */
class LeaveRequest extends Model
{
    protected $fillable = [
        'request_number', 'user_id',
        'type', 'leave_date', 'start_time', 'end_time', 'estimated_arrival', 'duration',
        'reason', 'emergency', 'emergency_reason', 'contactable',
        'status',
        'processed_by', 'processed_at', 'approval_note', 'rejection_reason',
        'hrd_processed_by', 'hrd_processed_at', 'hrd_decision', 'hrd_note',
        'email_status', 'email_sent_at', 'email_error',
        'telegram_status', 'telegram_sent_at', 'telegram_error',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'duration' => 'decimal:2',
            'emergency' => 'boolean',
            'contactable' => 'boolean',
            'processed_at' => 'datetime',
            'hrd_processed_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hrdProcessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hrd_processed_by');
    }

    /**
     * @return HasMany<LeaveRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'pending_manager'], true);
    }
}
