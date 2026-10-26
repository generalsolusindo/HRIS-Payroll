<?php

namespace App\Http\Requests\Hrd;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesCompanyDivision;
use App\Models\Company;
use App\Models\Division;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesCompanyDivision;

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
            'employment_type' => ['required', Rule::in(User::EMPLOYMENT_TYPES)],
            'employment_started_on' => ['required', 'date'],
            'probation_ends_on' => ['nullable', 'date'],
            'contract_ends_on' => ['nullable', 'date'],
            'employment_ended_on' => ['nullable', 'date'],
            'is_payroll_eligible' => ['required', 'boolean'],
            'is_allowance_eligible' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'division_id' => ['required', Rule::exists(Division::class, 'id')],
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
            // HRD manages users across every instansi (not just their
            // own), including moving a user between companies — so
            // company_id is only checked against the division chosen
            // alongside it, not against the acting HRD's own company.
            $this->assertDivisionBelongsToCompany(
                $validator,
                $this->input('company_id'),
                $this->input('division_id'),
            );
        });
    }
}
