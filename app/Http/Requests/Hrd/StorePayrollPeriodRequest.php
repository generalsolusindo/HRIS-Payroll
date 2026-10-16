<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(PayrollPeriod::class)->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'attendance_starts_on' => ['required', 'date'],
            'attendance_ends_on' => ['required', 'date', 'after_or_equal:attendance_starts_on'],
            'payment_due_on' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A different name for the exact same date range still hits
            // the payroll_period_dates_unique DB constraint — check it
            // here so that case surfaces as a normal validation error
            // instead of a 500 from the raw SQL exception.
            if ($validator->errors()->has('starts_on') || $validator->errors()->has('ends_on')) {
                return;
            }

            // whereDate(), not where() — the date cast stores
            // starts_on/ends_on with a "00:00:00" time component, so a
            // plain string comparison against the bare "Y-m-d" form input
            // never matches even when the dates are identical.
            $exists = PayrollPeriod::where('company_id', $this->user()->company_id)
                ->whereDate('starts_on', $this->input('starts_on'))
                ->whereDate('ends_on', $this->input('ends_on'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('starts_on', 'Sudah ada periode dengan rentang tanggal Mulai–Selesai yang sama di instansi ini.');
            }
        });
    }
}
