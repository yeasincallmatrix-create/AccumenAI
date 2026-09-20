<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DepositTdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deposit_date' => ['required', 'date'],
            'challan_no' => ['required', 'string', 'max:100'],
        ];
    }
}
