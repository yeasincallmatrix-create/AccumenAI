<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RecordPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
