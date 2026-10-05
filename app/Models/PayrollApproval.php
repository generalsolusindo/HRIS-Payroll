<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history row — one per workflow transition on a run. Never
 * updated after creation, so there is no updated_at column to manage.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property string $action submitted|revision_requested|approved|locked|reopen_requested|reopen_approved|reopen_rejected|superseded|cancelled
 * @property string $from_status
 * @property string $to_status
 * @property int|null $actor_id
 * @property array<string, mixed> $actor_snapshot
 * @property string|null $note
 */
class PayrollApproval extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'payroll_run_id', 'action', 'from_status', 'to_status',
        'actor_id', 'actor_snapshot', 'note',
    ];

    protected function casts(): array
    {
        return [
            'actor_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
