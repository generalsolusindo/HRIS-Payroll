<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Company ownership of the target period is checked in the
        // controller (ScopesToOwnCompany).
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PayrollPeriod $period */
        $period = $this->route('period');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(PayrollPeriod::class)
                    ->where(fn ($query) => $query->where('company_id', $period->company_id))
                    ->ignore($period->id),
            ],
            'starts_on'            => ['required', 'date'],
            'ends_on'              => ['required', 'date', 'after_or_equal:starts_on'],
            'attendance_starts_on' => ['required', 'date'],
            'attendance_ends_on'   => ['required', 'date', 'after_or_equal:attendance_starts_on'],
            'payment_due_on'       => ['nullable', 'date'],
        ];
    }
}
