<?php

namespace App\Http\Requests\Hrd;

use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:hrd middleware; company_id
        // is assigned server-side from the actor, never accepted from the
        // client.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique(Division::class)->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'default_base_salary' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'daily_attendance_allowance' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
        ];
    }
}
