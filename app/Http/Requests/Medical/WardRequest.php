<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(['general', 'cabin', 'icu', 'ccu', 'nicu', 'private'])],
            'total_beds' => 'required|integer|min:1',
            'daily_rate' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ward name is required.',
            'type.required' => 'Ward type is required.',
            'type.in' => 'Invalid ward type selected.',
            'total_beds.required' => 'Total beds is required.',
            'total_beds.min' => 'Total beds must be at least 1.',
            'daily_rate.required' => 'Daily rate is required.',
            'daily_rate.min' => 'Daily rate must be 0 or greater.',
        ];
    }
}
