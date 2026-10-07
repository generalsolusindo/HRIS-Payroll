<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:hrd middleware; company_id
        // is assigned server-side from the actor, never accepted from the
        // client.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique(PayrollPeriod::class)->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'attendance_starts_on' => ['required', 'date'],
            'attendance_ends_on' => ['required', 'date', 'after_or_equal:attendance_starts_on'],
            'payment_due_on' => ['nullable', 'date'],
        ];
    }
}
