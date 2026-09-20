<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCapitalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'authorized_capital' => 'required|numeric|min:0',
            'share_face_value' => 'required|numeric|min:0.01',
            'incorporation_date' => 'nullable|date',
            'registration_no' => 'nullable|string|max:50',
        ];
    }
}
