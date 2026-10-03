<?php

namespace App\Models;

use Database\Factories\UserFactory;
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
 * @property string|null $employee_number
 * @property string $name
 * @property string $email
 * @property string|null $phone
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
#[Fillable(['company_id', 'position_id', 'employee_number', 'name', 'email', 'phone', 'password'])]
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
        return $this->face_descriptor !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'face_descriptor' => 'encrypted:array',
            'face_enrolled_at' => 'datetime',
        ];
    }
}
