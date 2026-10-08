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
 * @property bool $is_active
 * @property string $default_base_salary
 * @property string $daily_attendance_allowance
 */
class Division extends Model
{
    protected $fillable = [
        'company_id', 'code', 'name', 'is_active',
        'default_base_salary', 'daily_attendance_allowance',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_base_salary' => 'decimal:2',
            'daily_attendance_allowance' => 'decimal:2',
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
     * @return HasMany<EmployeePayrollProfile, $this>
     */
    public function employeePayrollProfiles(): HasMany
    {
        return $this->hasMany(EmployeePayrollProfile::class);
    }
}
