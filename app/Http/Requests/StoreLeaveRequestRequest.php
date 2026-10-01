<?php

namespace App\Http\Requests;

use App\Services\LeaveTypeValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(LeaveTypeValidator::TYPES)],
            'leave_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'estimated_arrival' => ['nullable', 'date_format:H:i'],
            'duration' => ['nullable', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'emergency' => ['sometimes', 'boolean'],
            'emergency_reason' => ['nullable', 'string', 'max:2000'],
            'contactable' => ['nullable', 'boolean'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'mimes:jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            if (! is_string($type) || $validator->errors()->has('type')) {
                return;
            }

            $normalized = [
                'leave_date' => $this->input('leave_date'),
                'start_time' => $this->input('start_time'),
                'end_time' => $this->input('end_time'),
                'estimated_arrival' => $this->input('estimated_arrival'),
                'duration' => $this->input('duration'),
                'emergency' => $this->boolean('emergency'),
                'contactable' => $this->has('contactable') ? $this->boolean('contactable') : null,
            ];

            foreach ((new LeaveTypeValidator)->validate($type, $normalized) as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            if ($type === 'sick' && (float) $this->input('duration', 0) > 1 && ! $this->hasFile('attachments')) {
                $validator->errors()->add('attachments', 'Surat dokter wajib dilampirkan untuk sakit lebih dari 1 hari.');
            }
        });
    }
}
