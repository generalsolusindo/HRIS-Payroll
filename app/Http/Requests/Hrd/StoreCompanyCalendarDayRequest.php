<?php

namespace App\Http\Requests\Hrd;

use App\Models\CompanyCalendarDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyCalendarDayRequest extends FormRequest
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
            // Role middleware only checks the "hrd" role name, not the
            // tenant boundary — an HRD must never add a calendar day for
            // a company other than their own.
            'company_id' => ['required', Rule::in([$this->user()->company_id])],
            'date' => [
                'required', 'date',
                Rule::unique(CompanyCalendarDay::class)->where(fn ($query) => $query->where('company_id', $this->input('company_id'))),
            ],
            'type' => ['required', Rule::in(['workday', 'rest_day', 'holiday'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
