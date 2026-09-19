<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;

class LabAnalyzerMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        return [
            'vendor_code' => 'required|string|max:50',
            'vendor_name' => 'nullable|string|max:200',
            'universal_code' => 'required|string|max:50',
            'parameter_key' => 'nullable|string|max:50',
            'lab_test_id' => 'nullable|exists:lab_tests,id',
            'unit_from' => 'nullable|string|max:30',
            'unit_to' => 'nullable|string|max:30',
            'conversion_factor' => 'nullable|numeric|min:0',
            'ref_low' => 'nullable|numeric',
            'ref_high' => 'nullable|numeric',
            'ref_range_text' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
