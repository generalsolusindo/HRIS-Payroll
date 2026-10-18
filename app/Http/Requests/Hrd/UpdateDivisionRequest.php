<?php

namespace App\Http\Requests\Hrd;

use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Company ownership of the target division is checked in the
        // controller (ScopesToOwnCompany); company_id itself is never
        // reassignable from the client.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Division $division */
        $division = $this->route('division');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Division::class)
                    ->where(fn ($query) => $query->where('company_id', $division->company_id))
                    ->ignore($division->id),
            ],
            'is_active' => ['required', 'boolean'],
            'base_salary' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
        ];
    }
}
