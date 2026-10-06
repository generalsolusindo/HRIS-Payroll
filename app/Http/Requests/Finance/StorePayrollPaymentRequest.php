<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:finance middleware.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
