<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stock adjustment (physical count, damage, expiry write-off).
 * Listed in the Phase 3 execution plan; no draft code was provided.
 */
class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        return [
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'new_quantity.required' => 'New quantity is required.',
            'new_quantity.min' => 'Quantity cannot be negative.',
            'reason.required' => 'Please state a reason for the adjustment.',
        ];
    }
}
