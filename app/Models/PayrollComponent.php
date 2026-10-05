<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string $type earning|deduction|employer_contribution
 * @property string $category base_salary|allowance|incentive|overtime|attendance_reward|attendance_deduction|insurance|tax|other
 * @property bool $is_active
 */
class PayrollComponent extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'type', 'category', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function employeeSalaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /**
     * @return HasMany<PayrollEntryLine, $this>
     */
    public function entryLines(): HasMany
    {
        return $this->hasMany(PayrollEntryLine::class);
    }
}
