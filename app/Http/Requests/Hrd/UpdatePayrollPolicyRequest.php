<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollPolicyRequest extends FormRequest
{
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
            'late_deduction_per_minute' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'absence_deduction_per_day' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'perfect_attendance_reward' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'overtime_rate_per_hour' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
