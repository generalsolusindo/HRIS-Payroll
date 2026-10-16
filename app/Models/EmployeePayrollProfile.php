<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int $division_id
 * @property int|null $position_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon $employment_started_on
 * @property Carbon|null $employment_ended_on
 * @property string $employment_type
 * @property string|null $tax_identity
 * @property string|null $insurance_identity
 * @property int|null $created_by
 */
class EmployeePayrollProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'division_id', 'position_id',
        'effective_from', 'effective_until',
        'employment_started_on', 'employment_ended_on', 'employment_type',
        'tax_identity', 'insurance_identity', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'employment_started_on' => 'date',
            'employment_ended_on' => 'date',
            // Tax/insurance identity — biometric-adjacent sensitivity, same
            // reasoning as User::face_descriptor: never plaintext at rest.
            'tax_identity' => 'encrypted',
            'insurance_identity' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /**
     * @return HasMany<PayrollEntry, $this>
     */
    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
