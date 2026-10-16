<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_period_id
 * @property int $version
 * @property int|null $supersedes_run_id
 * @property int $payroll_policy_id
 * @property array<string, mixed> $policy_snapshot
 * @property array<string, mixed> $company_snapshot
 * @property string $status draft|submitted|revision_requested|approved|locked|partially_paid|paid|superseded|cancelled
 * @property int $employee_count
 * @property string $gross_total
 * @property string $deduction_total
 * @property string $reward_total
 * @property string $employer_contribution_total
 * @property string $net_total
 * @property int|null $created_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $locked_at
 * @property Carbon|null $shared_at
 * @property string|null $revision_reason
 */
class PayrollRun extends Model
{
    protected $fillable = [
        'payroll_period_id', 'version', 'supersedes_run_id', 'payroll_policy_id',
        'policy_snapshot', 'company_snapshot', 'status', 'employee_count',
        'gross_total', 'deduction_total', 'reward_total', 'employer_contribution_total', 'net_total',
        'created_by', 'submitted_at', 'approved_at', 'locked_at', 'shared_at', 'revision_reason',
    ];

    protected function casts(): array
    {
        return [
            'policy_snapshot' => 'array',
            'company_snapshot' => 'array',
            'gross_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'reward_total' => 'decimal:2',
            'employer_contribution_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'shared_at' => 'datetime',
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
     * @return BelongsTo<PayrollPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PayrollPolicy::class, 'payroll_policy_id');
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function supersedesRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_run_id');
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PayrollEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * @return HasMany<PayrollApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(PayrollApproval::class);
    }
}
