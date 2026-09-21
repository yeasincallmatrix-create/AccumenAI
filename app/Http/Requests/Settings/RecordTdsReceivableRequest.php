<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RecordTdsReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'party_id' => 'required|integer|exists:parties,id',
            'invoice_id' => 'nullable|integer',
            'reference_no' => 'nullable|string|max:50',
            'gross_amount' => 'required|numeric|min:0.01',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'deduction_date' => 'required|date',
            'tax_period' => 'required|string|max:20',
            'financial_year' => 'required|string|max:20',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
