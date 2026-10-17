<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_period_id
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property string $status pending|approved|rejected
 * @property string $reason
 * @property string|null $decision_note
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 */
class PayrollPeriodDeletionRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payroll_period_id', 'requested_by', 'requested_by_snapshot',
        'decided_by', 'decided_by_snapshot', 'status', 'reason', 'decision_note',
        'requested_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_by_snapshot' => 'array',
            'decided_by_snapshot' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}