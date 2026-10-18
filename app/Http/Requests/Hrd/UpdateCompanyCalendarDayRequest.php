<?php

namespace App\Http\Requests\Hrd;

use App\Models\CompanyCalendarDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyCalendarDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Company ownership of the target day is checked in the
        // controller (ScopesToOwnCompany).
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CompanyCalendarDay $day */
        $day = $this->route('day');

        return [
            'date' => [
                'required', 'date',
                Rule::unique(CompanyCalendarDay::class)
                    ->where(fn ($query) => $query->where('company_id', $day->company_id))
                    ->ignore($day->id),
            ],
            'type' => ['required', Rule::in(['workday', 'rest_day', 'holiday'])],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $day->company_id)->where('role', 'karyawan')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
