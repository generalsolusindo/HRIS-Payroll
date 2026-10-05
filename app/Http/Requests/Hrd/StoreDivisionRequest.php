<?php

namespace App\Http\Requests\Hrd;

use App\Models\Company;
use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDivisionRequest extends FormRequest
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
            'company_id' => ['required', Rule::exists(Company::class, 'id')],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique(Division::class)->where(fn ($query) => $query->where('company_id', $this->input('company_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
