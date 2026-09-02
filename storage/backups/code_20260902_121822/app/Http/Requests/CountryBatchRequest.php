<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CountryBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['required', 'integer', 'exists:countries,id'],
            'action' => ['required', 'string', 'in:enable,disable,assign_grade_scale,assign_academic_structure,assign_default_modules,sync_all'],
        ];
    }

    public function messages(): array
    {
        return [
            'country_ids.required' => 'Select at least one country.',
            'country_ids.min' => 'Select at least one country.',
            'country_ids.*.exists' => 'One or more selected countries are invalid.',
            'action.required' => 'Batch action is required.',
            'action.in' => 'Invalid batch action.',
        ];
    }
}
