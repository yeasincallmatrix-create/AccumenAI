<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DeclareDividendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'declared_date' => 'required|date',
            'record_date' => 'nullable|date|after_or_equal:declared_date',
            'payment_date' => 'nullable|date|after_or_equal:record_date',
            'financial_year' => 'required|string|max:20',
            'total_dividend' => 'required|numeric|min:0.01',
            'board_resolution' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
