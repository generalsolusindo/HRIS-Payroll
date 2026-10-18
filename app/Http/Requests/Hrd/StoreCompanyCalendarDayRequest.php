<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'date' => ['required', 'date'],
            // A range mode (e.g. "libur Idul Fitri 31 Mar - 2 Apr") —
            // defaults to a single day when left out.
            'ends_on' => ['nullable', 'date', 'after_or_equal:date'],
            'type' => ['required', Rule::in(['workday', 'rest_day', 'holiday'])],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)->where('role', 'karyawan')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('date') || $validator->errors()->has('ends_on') || ! $this->filled('ends_on')) {
                return;
            }

            // A capped span so one submit can't silently create months of
            // rows by mistake — well beyond any real holiday stretch.
            if (Carbon::parse($this->input('date'))->diffInDays(Carbon::parse($this->input('ends_on'))) > 60) {
                $validator->errors()->add('ends_on', 'Rentang tanggal maksimal 60 hari dalam satu kali tambah.');
            }
        });
    }
}
