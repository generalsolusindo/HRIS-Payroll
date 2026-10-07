<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:hrd middleware; company_id
        // is assigned server-side from the actor, never accepted from the
        // client, so there is no cross-company input to authorize here.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique(PayrollComponent::class)->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
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
