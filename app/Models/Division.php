<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_active
 * @property string $base_salary
 */
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'name', 'is_active',
        'base_salary',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'base_salary' => 'decimal:2',
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
