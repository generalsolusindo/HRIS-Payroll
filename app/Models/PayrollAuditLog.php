<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row — never updated after creation, so there is no
 * updated_at column to manage.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $payroll_run_id
 * @property int|null $attendance_review_id
 * @property int|null $actor_id
 * @property array<string, mixed> $actor_snapshot
 * @property string $entity_type
 * @property int $entity_id
 * @property string $action
 * @property array<string, mixed>|null $before_values
 * @property array<string, mixed>|null $after_values
 * @property string $reason
 */
class PayrollAuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'attendance_review_id', 'actor_id', 'actor_snapshot',
        'entity_type', 'entity_id', 'action', 'before_values', 'after_values', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'actor_snapshot' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<AttendanceReview, $this>
     */
    public function attendanceReview(): BelongsTo
    {
        return $this->belongsTo(AttendanceReview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
