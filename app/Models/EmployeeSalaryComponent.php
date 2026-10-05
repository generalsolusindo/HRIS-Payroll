<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_payroll_profile_id
 * @property int $payroll_component_id
 * @property string $amount
 * @property string $basis monthly|daily|hourly|fixed
 */
class EmployeeSalaryComponent extends Model
{
    protected $fillable = ['employee_payroll_profile_id', 'payroll_component_id', 'amount', 'basis'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EmployeePayrollProfile, $this>
     */
    public function employeePayrollProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollProfile::class);
    }

    /**
     * @return BelongsTo<PayrollComponent, $this>
     */
    public function payrollComponent(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class);
    }
}
