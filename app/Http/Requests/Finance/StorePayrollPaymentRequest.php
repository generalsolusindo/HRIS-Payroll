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
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Client-generated once per dialog session (not per click) and
            // resubmitted unchanged on retry, so a dropped-response retry
            // reuses the same key instead of minting a new one — see
            // PayrollPaymentController::storePayment's duplicate-key catch.
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
