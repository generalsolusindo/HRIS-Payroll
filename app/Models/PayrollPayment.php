<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_entry_id
 * @property string $idempotency_key
 * @property string $amount
 * @property string $method bank_transfer|cash|other
 * @property string $status pending|confirmed|failed|voided
 * @property string|null $reference
 * @property string|null $proof_path
 * @property array<string, mixed>|null $destination_snapshot
 * @property Carbon|null $paid_at
 * @property int|null $recorded_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property string|null $note
 */
class PayrollPayment extends Model
{
    protected $fillable = [
        'payroll_entry_id', 'idempotency_key', 'amount', 'method', 'status',
        'reference', 'proof_path', 'destination_snapshot', 'paid_at',
        'recorded_by', 'confirmed_by', 'confirmed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'destination_snapshot' => 'encrypted:array',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
