<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_entry_id
 * @property int $user_id
 * @property string $message
 * @property string $status new|in_review|resolved
 * @property string|null $hrd_note
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 */
class PayrollEntryComplaint extends Model
{
    protected $fillable = [
        'payroll_entry_id', 'user_id', 'message', 'status', 'hrd_note', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
