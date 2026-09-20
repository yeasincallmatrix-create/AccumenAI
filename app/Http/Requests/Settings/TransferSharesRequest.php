<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TransferSharesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'from_shareholder_id' => 'required|integer|exists:shareholders,id',
            'to_shareholder_id' => 'required|integer|exists:shareholders,id|different:from_shareholder_id',
            'shares' => 'required|integer|min:1',
            'face_value' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
