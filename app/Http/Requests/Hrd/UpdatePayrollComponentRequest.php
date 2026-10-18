<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Company ownership of the target component is checked in the
        // controller (ScopesToOwnCompany); the component's code is never
        // accepted from the client here — see PayrollComponent::update().
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution'])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
