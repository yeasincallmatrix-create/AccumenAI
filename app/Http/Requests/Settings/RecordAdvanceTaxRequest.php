<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RecordAdvanceTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'financial_year' => ['required', 'string', 'max:20'],
            'quarter' => ['required', 'string', 'in:Q1,Q2,Q3,Q4'],
            'estimated_income' => ['required', 'numeric', 'min:0'],
            'entity_type' => ['nullable', 'string', 'max:50'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
