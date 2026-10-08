<?php

namespace App\Http\Requests\Hrd;

use App\Models\Division;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePayrollProfileRequest extends FormRequest
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
            'division_id' => ['required', 'integer'],
            'effective_from' => ['required', 'date'],
            'employment_started_on' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(['permanent', 'contract', 'probation', 'intern'])],
            'components' => ['required', 'array', 'min:1'],
            'components.*.payroll_component_id' => ['required', 'integer', 'distinct'],
            'components.*.amount' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var User $targetUser */
            $targetUser = $this->route('user');

            $divisionId = $this->input('division_id');
            if ($divisionId && ! Division::where('id', $divisionId)->where('company_id', $targetUser->company_id)->exists()) {
                $validator->errors()->add('division_id', 'Divisi yang dipilih tidak tersedia untuk instansi karyawan ini.');
            }

            $componentIds = collect((array) $this->input('components', []))->pluck('payroll_component_id')->filter();
            if ($componentIds->isNotEmpty()) {
                $validCount = PayrollComponent::whereIn('id', $componentIds)
                    ->where('company_id', $targetUser->company_id)
                    ->where('type', 'earning')->where('is_active', true)
                    ->count();
                if ($validCount !== $componentIds->unique()->count()) {
                    $validator->errors()->add('components', 'Salah satu komponen gaji tidak tersedia untuk instansi karyawan ini.');
                }
            }
        });
    }
}
