<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(PayrollPeriod::class)->where(
                    fn ($query) => $query->where('company_id', $this->user()->company_id)
                ),
            ],
            'starts_on'            => ['required', 'date'],
            'ends_on'              => ['required', 'date', 'after_or_equal:starts_on'],
            'attendance_starts_on' => ['required', 'date'],
            'attendance_ends_on'   => ['required', 'date', 'after_or_equal:attendance_starts_on'],
            'payment_due_on'       => ['nullable', 'date'],
        ];
    }
}
