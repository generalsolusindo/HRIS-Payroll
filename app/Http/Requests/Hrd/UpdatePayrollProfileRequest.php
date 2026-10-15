<?php

namespace App\Http\Requests\Hrd;

use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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

            $componentIds = collect((array) $this->input('components', []))->pluck('payroll_component_id')->filter();
            if ($componentIds->isNotEmpty()) {
                // System-managed components (Reward Kehadiran Sempurna,
                // Lembur, Potongan Alpha/Keterlambatan, etc.) must never be
                // set here even via a crafted request — the payroll run
                // calculates and adds them itself, so a manual amount would
                // double up on top of it. Both earning and deduction types
                // are otherwise allowed — Profil Gaji has a manual section
                // for each.
                $validCount = PayrollComponent::whereIn('id', $componentIds)
                    ->where('company_id', $targetUser->company_id)
                    ->whereIn('type', ['earning', 'deduction'])->where('is_active', true)
                    ->whereNotIn('code', PayrollComponent::SYSTEM_CODES)
                    ->count();
                if ($validCount !== $componentIds->unique()->count()) {
                    $validator->errors()->add('components', 'Salah satu komponen gaji tidak tersedia untuk instansi karyawan ini.');
                }
            }
        });
    }
}
