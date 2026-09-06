<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'prescription_item_id' => [
                'required',
                Rule::exists('prescription_items', 'id'),
            ],
            'stock_id' => [
                'required',
                Rule::exists('pharmacy_stock', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'quantity_dispensed' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'prescription_item_id.required' => 'Please select a prescription item.',
            'stock_id.required' => 'Please select a stock batch.',
            'stock_id.exists' => 'Selected batch does not exist.',
            'quantity_dispensed.required' => 'Quantity is required.',
            'quantity_dispensed.min' => 'Quantity must be at least 1.',
        ];
    }
}
