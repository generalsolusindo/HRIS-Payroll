<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'telegram_chat_id'];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<OfficeLocation, $this>
     */
    public function officeLocations(): HasMany
    {
        return $this->hasMany(OfficeLocation::class);
    }

    /**
     * @return HasMany<WorkSchedule, $this>
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * @return BelongsToMany<Position, $this>
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class);
    }

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /**
     * @return HasMany<PayrollComponent, $this>
     */
    public function payrollComponents(): HasMany
    {
        return $this->hasMany(PayrollComponent::class);
    }

    /**
     * @return HasMany<PayrollPolicy, $this>
     */
    public function payrollPolicies(): HasMany
    {
        return $this->hasMany(PayrollPolicy::class);
    }

    /**
     * @return HasMany<PayrollPeriod, $this>
     */
    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    /**
     * @return HasMany<CompanyCalendarDay, $this>
     */
    public function calendarDays(): HasMany
    {
        return $this->hasMany(CompanyCalendarDay::class);
    }
}
