<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Company ownership of the target component is checked in the
        // controller (ScopesToOwnCompany); company_id itself is never
        // reassignable from the client — a component stays in the company
        // it was created in.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique(PayrollComponent::class)
                    ->where(fn ($query) => $query->where('company_id', $component->company_id))
                    ->ignore($component->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution'])],
            'category' => ['required', Rule::in([
                'base_salary', 'allowance', 'incentive', 'overtime', 'attendance_reward',
                'attendance_deduction', 'insurance', 'tax', 'other',
            ])],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
