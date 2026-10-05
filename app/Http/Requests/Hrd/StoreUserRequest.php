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
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_number' => ['nullable', 'string', 'max:255', Rule::unique(User::class)],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'position_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(['karyawan', 'hrd', 'admin', 'finance'])],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected'])],
            'password' => $this->passwordRules(),
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
