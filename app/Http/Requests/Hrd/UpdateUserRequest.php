<?php

namespace App\Http\Requests\Hrd;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesCompanyPosition;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesCompanyPosition;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user->id),
            'employment_type' => ['nullable', Rule::in(User::EMPLOYMENT_TYPES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'position_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(['karyawan', 'hrd', 'admin', 'finance'])],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            // Optional on edit — left blank means "don't change".
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            // Optional on edit — left blank means "don't change the password".
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Role middleware only checks the "hrd" role name, not the
            // tenant boundary — an HRD must never move a user into a
            // company other than their own. (Whether the target user
            // already belongs to another company is checked separately,
            // in the controller, before this request is even validated.)
            if ($this->filled('company_id') && (int) $this->input('company_id') !== $this->user()->company_id) {
                $validator->errors()->add('company_id', 'Anda hanya dapat mengelola pengguna pada instansi Anda sendiri.');

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
