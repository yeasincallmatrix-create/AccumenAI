<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PharmacyStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'medicine_id' => [
                'required',
                Rule::exists('medicines', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'batch_number' => 'required|string|max:50',
            'manufacturing_date' => 'nullable|date|before_or_equal:today',
            'expiry_date' => 'required|date|after:today',
            'quantity_received' => 'required|integer|min:1',
            'current_quantity' => 'required|integer|min:0|lte:quantity_received',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'supplier_invoice_no' => 'nullable|string|max:50',
            'received_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'medicine_id.required' => 'Please select a medicine.',
            'medicine_id.exists' => 'Selected medicine does not exist.',
            'batch_number.required' => 'Batch number is required.',
            'expiry_date.required' => 'Expiry date is required.',
            'expiry_date.after' => 'Expiry date must be in the future.',
            'quantity_received.required' => 'Quantity received is required.',
            'quantity_received.min' => 'Quantity received must be at least 1.',
            'current_quantity.lte' => 'Current quantity cannot exceed quantity received.',
        ];
    }
}
