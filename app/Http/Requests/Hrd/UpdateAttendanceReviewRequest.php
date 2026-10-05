<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceReviewRequest extends FormRequest
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
            'classification' => ['required', Rule::in([
                'unresolved', 'present', 'leave', 'sick', 'permission',
                'business_trip', 'absent', 'rest_day', 'holiday',
            ])],
            'correction_reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'max:5120'],
        ];
    }
}
