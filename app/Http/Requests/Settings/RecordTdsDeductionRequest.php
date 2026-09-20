<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RecordTdsDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'payee_name' => ['required', 'string', 'max:200'],
            'payee_tin' => ['nullable', 'string', 'max:50'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'deduction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
