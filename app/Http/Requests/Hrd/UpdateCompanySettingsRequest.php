<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the route's auth/active/role:hrd middleware.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'director_signature' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
