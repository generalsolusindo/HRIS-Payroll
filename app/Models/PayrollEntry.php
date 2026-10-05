<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $payroll_run_id
 * @property int $user_id
 * @property int $employee_payroll_profile_id
 * @property int $division_id
 * @property array<string, mixed> $employee_snapshot
 * @property string $division_name
 * @property string $position_name
 * @property array<string, mixed> $attendance_snapshot
 * @property array<string, mixed> $compensation_snapshot
 * @property array<string, mixed>|null $payment_destination
 * @property string $gross_amount
 * @property string $deduction_amount
 * @property string $reward_amount
 * @property string $employer_contribution_amount
 * @property string $net_amount
 * @property string|null $note
 */
class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_run_id', 'user_id', 'employee_payroll_profile_id', 'division_id',
        'employee_snapshot', 'division_name', 'position_name',
        'attendance_snapshot', 'compensation_snapshot', 'payment_destination',
        'gross_amount', 'deduction_amount', 'reward_amount', 'employer_contribution_amount', 'net_amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'employee_snapshot' => 'array',
            'attendance_snapshot' => 'array',
            'compensation_snapshot' => 'array',
            // Serialized bank destination — never include in a plain JSON
            // snapshot column; this is its own encrypted-at-rest field.
            'payment_destination' => 'encrypted:array',
            'gross_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'reward_amount' => 'decimal:2',
            'employer_contribution_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<EmployeePayrollProfile, $this>
     */
    public function employeePayrollProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollProfile::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<PayrollEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollEntryLine::class);
    }

    /**
     * @return HasMany<PayrollPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    /**
     * @return HasMany<PayrollPayslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }
}
