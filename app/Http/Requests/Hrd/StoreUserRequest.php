<?php

namespace App\Http\Requests\Hrd;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesCompanyPosition;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules, ValidatesCompanyPosition;

    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:hrd middleware.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'employment_type' => ['nullable', Rule::in(User::EMPLOYMENT_TYPES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'position_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(['karyawan', 'hrd', 'admin', 'finance'])],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Role middleware only checks the "hrd" role name, not the
            // tenant boundary — an HRD must never create a user in a
            // company other than their own.
            if ($this->filled('company_id') && (int) $this->input('company_id') !== $this->user()->company_id) {
                $validator->errors()->add('company_id', 'Anda hanya dapat membuat pengguna pada instansi Anda sendiri.');

                return;
            }

            $this->assertPositionBelongsToCompany(
                $validator,
                $this->input('company_id'),
                $this->input('position_id'),
            );
        });
    }
}
