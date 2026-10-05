<?php

namespace App\Http\Requests\Hrd;

use App\Models\Company;
use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
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
        /** @var Division $division */
        $division = $this->route('division');

        return [
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique(Division::class)
                    ->where(fn ($query) => $query->where('company_id', $this->input('company_id')))
                    ->ignore($division->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
