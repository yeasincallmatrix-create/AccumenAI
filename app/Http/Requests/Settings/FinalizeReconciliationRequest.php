<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'financial_year' => 'required|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
