<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        // Route param is {test} (singular of the `lab/tests` resource).
        $test = $this->route('test');
        $testId = $test instanceof \App\Models\Medical\LabTest ? $test->id : null;

        return [
            // `lab_tests.code` is globally unique in the Phase 0 schema.
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('lab_tests', 'code')->ignore($testId),
            ],
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'normal_range' => 'nullable|string',
            'unit' => 'nullable|string|max:20',
            'price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Test code is required.',
            'code.unique' => 'This test code already exists.',
            'name.required' => 'Test name is required.',
            'price.required' => 'Price is required.',
            'price.min' => 'Price must be 0 or greater.',
        ];
    }
}
