<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'nid' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'capital' => 'required|numeric|min:0',
            'share_percent' => 'required|numeric|min:0|max:100',
            'joined_at' => 'nullable|date',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
