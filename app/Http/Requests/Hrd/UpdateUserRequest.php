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
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_number' => ['nullable', 'string', 'max:255', Rule::unique(User::class)->ignore($user->id)],
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
        $validator->after(fn (Validator $validator) => $this->assertPositionBelongsToCompany(
            $validator,
            $this->input('company_id'),
            $this->input('position_id'),
        ));
    }
}
