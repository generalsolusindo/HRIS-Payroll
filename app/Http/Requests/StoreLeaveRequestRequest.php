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
        // HRD and Manager are the two approval stages for Izin — letting
        // either submit their own would mean one of those stages is
        // asked to approve its own submission.
        return ! in_array($this->user()->role, ['hrd', 'admin'], true);
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
            'reason' => ['required', 'string', 'max:2000'],
            'emergency' => ['sometimes', 'boolean'],
            'emergency_reason' => ['nullable', 'string', 'max:2000'],
            'contactable' => ['nullable', 'boolean'],
            // Only meaningful for type=late (checked in LeaveTypeValidator);
            // harmless to accept for every type since it is never persisted.
            'agreement' => ['sometimes', 'boolean'],
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

            foreach ((new LeaveTypeValidator)->validate($type, $this->normalized()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            if ($type === 'sick' && (float) $this->input('duration', 0) > 1 && ! $this->hasFile('attachments')) {
                $validator->errors()->add('attachments', 'Surat dokter wajib dilampirkan untuk sakit lebih dari 1 hari.');
            }
        });
    }

    /**
     * The fields LeaveTypeValidator cares about, coerced the same way
     * regardless of caller — shared by withValidator() and by the
     * controller when it builds the row to store.
     *
     * @return array<string, mixed>
     */
    private function normalized(): array
    {
        return [
            'leave_date' => $this->input('leave_date'),
            'start_time' => $this->input('start_time'),
            'end_time' => $this->input('end_time'),
            'estimated_arrival' => $this->input('estimated_arrival'),
            'duration' => $this->input('duration'),
            'emergency' => $this->boolean('emergency'),
            'emergency_reason' => $this->input('emergency_reason'),
            'contactable' => $this->has('contactable') ? $this->boolean('contactable') : null,
            'agreement' => $this->boolean('agreement'),
        ];
    }

    /**
     * Attributes ready to pass to LeaveRequest::create(), minus
     * request_number and user_id which the controller fills in.
     *
     * @return array<string, mixed>
     */
    public function leaveRequestAttributes(): array
    {
        return [
            'type' => $this->input('type'),
            'reason' => $this->input('reason'),
            ...$this->normalized(),
        ];
    }
}
