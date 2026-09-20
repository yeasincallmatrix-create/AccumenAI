<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareholderRequest extends FormRequest
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
            'nid' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'shares' => 'required|integer|min:1',
            'face_value' => 'required|numeric|min:0',
            'share_percent' => 'required|numeric|min:0|max:100',
            'certificate_no' => 'nullable|string|max:50',
            'issued_at' => 'nullable|date',
            'is_director' => 'boolean',
            'director_designation' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_director' => $this->boolean('is_director'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
