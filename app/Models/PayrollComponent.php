<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string $type earning|deduction|employer_contribution
 * @property bool $is_active
 */
class PayrollComponent extends Model
{
    /**
     * Codes PayrollRunController auto-creates per company and attaches
     * itself from attendance/overtime data at calculation time — these
     * must never be manually configurable as a fixed monthly amount on an
     * EmployeePayrollProfile (see PayrollProfileController), or the
     * calculated amount would get paid on top of a manually-set one.
     * ATTENDANCE_LATE/ATTENDANCE_ABSENCE are 'deduction' type so they're
     * already excluded wherever only 'earning' components are offered;
     * listed here anyway so this stays the single source of truth.
     */
    public const SYSTEM_CODES = [
        'ATTENDANCE_LATE',
        'ATTENDANCE_ABSENCE',
        'ATTENDANCE_PERFECT',
        'OVERTIME',
        'ATTENDANCE_ALLOWANCE',
    ];

    protected $fillable = ['company_id', 'code', 'name', 'type', 'is_active'];

    /**
     * HRD only ever names a component — code is an internal identifier
     * (used by PayrollRunController to find/create the system-managed
     * components, see SYSTEM_CODES) that would otherwise be a confusing,
     * purely-cosmetic text field for HRD to fill in by hand. Generated
     * once at creation and never changed afterwards.
     */
    public static function generateCode(int $companyId, string $name): string
    {
        $base = Str::of($name)->upper()->ascii()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(40, '');
        $base = $base->isEmpty() ? 'KOMPONEN' : $base->value();

        $code = $base;
        $attempt = 1;
        while (
            in_array($code, self::SYSTEM_CODES, true)
            || static::where('company_id', $companyId)->where('code', $code)->exists()
        ) {
            $attempt++;
            $code = "{$base}_{$attempt}";
        }

        return $code;
    }

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
