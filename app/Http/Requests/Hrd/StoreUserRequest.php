<?php

namespace App\Http\Requests\Hrd;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesCompanyDivision;
use App\Models\Company;
use App\Models\Division;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules, ValidatesCompanyDivision;

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
            'employment_type' => ['required', Rule::in(User::EMPLOYMENT_TYPES)],
            'employment_started_on' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'division_id' => ['required', Rule::exists(Division::class, 'id')],
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
            // HRD manages users across every instansi (not just their
            // own), so company_id is only checked against the division
            // chosen alongside it — not against the acting HRD's company.
            $this->assertDivisionBelongsToCompany(
                $validator,
                $this->input('company_id'),
                $this->input('division_id'),
            );
        });
    }
}
