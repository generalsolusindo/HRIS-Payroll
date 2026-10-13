<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HRD is Lembur's only approval stage — letting HRD submit its
        // own would mean that stage is asked to approve its own request.
        return $this->user()->role !== 'hrd';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
