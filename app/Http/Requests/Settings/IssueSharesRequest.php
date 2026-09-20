<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class IssueSharesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'shareholder_id' => 'required|integer|exists:shareholders,id',
            'shares' => 'required|integer|min:1',
            'face_value' => 'required|numeric|min:0.01',
            'premium_per_share' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
