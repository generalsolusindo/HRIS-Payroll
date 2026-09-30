<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Fortify's own registration route carries no rate limiter (unlike
     * login/two-factor/passkeys, which config('fortify.limiters.*') covers),
     * so it's enforced here instead of fighting the package's route registration.
     */
    private const MAX_ATTEMPTS_PER_MINUTE = 10;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $throttleKey = 'register:'.Request::ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan registrasi. Coba lagi dalam beberapa menit.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'max:20'],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'position_id' => ['required', 'integer'],
            'password' => $this->passwordRules(),
        ])->validate();

        $companyHasPosition = Company::whereKey($input['company_id'])
            ->whereHas('positions', fn ($query) => $query->whereKey($input['position_id']))
            ->exists();

        if (! $companyHasPosition) {
            throw ValidationException::withMessages([
                'position_id' => 'Jabatan/Divisi yang dipilih tidak tersedia untuk instansi ini.',
            ]);
        }

        return User::create([
            'company_id' => $input['company_id'],
            'position_id' => $input['position_id'],
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);
    }
}
