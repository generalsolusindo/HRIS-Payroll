<?php

namespace App\Models;

use App\Services\EmployeeNumber;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int $company_id
 * @property int $position_id
 * @property int|null $division_id
 * @property string|null $employment_type
 * @property Carbon|null $employment_started_on
 * @property string|null $employee_number
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_holder
 * @property string $role
 * @property string $status
 * @property array<int, float>|null $face_descriptor
 * @property Carbon|null $face_enrolled_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'position_id', 'division_id', 'employee_number', 'employment_type', 'employment_started_on', 'name', 'email', 'phone', 'password', 'bank_name', 'bank_account_number', 'bank_account_holder'])]
// face_descriptor is deliberately never mass-assignable or serialized —
// it's biometric data and must only ever be set via an explicit enrollment
// code path, never via fill()/create(), and never sent to the frontend
// (the "encrypted:array" cast below decrypts it on access, so toArray()/JSON
// would otherwise leak the raw descriptor through the shared Inertia props).
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'face_descriptor'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const EMPLOYMENT_TYPES = ['permanent', 'contract', 'part_time', 'daily', 'freelance', 'outsourcing', 'intern', 'volunteer', 'probation'];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (($user->role ?? 'karyawan') === 'karyawan' && ! $user->employee_number) {
                $user->employee_number = EmployeeNumber::next();
            }
        });
        static::saved(function (User $user): void {
            if ($user->wasChanged('employment_type') && $user->employment_type !== null) {
                $user->payrollProfiles()->whereNull('effective_until')->update(['employment_type' => $user->employment_type]);
            }
            if ($user->wasChanged('employment_started_on') && $user->employment_started_on !== null) {
                $user->payrollProfiles()->whereNull('effective_until')->update(['employment_started_on' => $user->employment_started_on]);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Position and Division were duplicate org-unit concepts — this is
     * the one being kept (see the 2026_10_15_130040 migration). position()
     * stays in place until the rest of the app has moved over to this.
     *
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** 
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<Leave, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<EmployeePayrollProfile, $this>
     */
    public function payrollProfiles(): HasMany
    {
        return $this->hasMany(EmployeePayrollProfile::class);
    }

    /**
     * @return HasMany<AttendanceReview, $this>
     */
    public function attendanceReviews(): HasMany
    {
        return $this->hasMany(AttendanceReview::class);
    }

    /**
     * @return HasMany<OvertimeRequest, $this>
     */
    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function isHrd(): bool
    {
        return $this->role === 'hrd';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasFaceEnrolled(): bool
    {
        try {
            $descriptor = $this->readStoredFaceDescriptor();
        } catch (DecryptException) {
            return false;
        }

        if (! is_array($descriptor) || ! array_is_list($descriptor) || count($descriptor) !== 128) {
            return false;
        }

        foreach ($descriptor as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read through the encrypted cast without assuming stored data is valid.
     *
     * @throws DecryptException
     */
    private function readStoredFaceDescriptor(): mixed
    {
        return $this->getAttribute('face_descriptor');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_started_on' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'face_descriptor' => 'encrypted:array',
            'face_enrolled_at' => 'datetime',
            // Same sensitivity reasoning as face_descriptor — never plaintext at rest.
            'bank_account_number' => 'encrypted',
            'bank_account_holder' => 'encrypted',
        ];
    }
}
