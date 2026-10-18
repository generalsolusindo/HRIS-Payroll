<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            if ($validator->errors()->has('starts_on') || $validator->errors()->has('ends_on')) {
                return;
            }

            /** @var PayrollPeriod $period */
            $period = $this->route('period');

            $exists = PayrollPeriod::where('company_id', $period->company_id)
                ->whereKeyNot($period->id)
                ->whereDate('starts_on', $this->input('starts_on'))
                ->whereDate('ends_on', $this->input('ends_on'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('starts_on', 'Sudah ada periode dengan rentang tanggal Mulai–Selesai yang sama di instansi ini.');
            }
        });
    }
}
