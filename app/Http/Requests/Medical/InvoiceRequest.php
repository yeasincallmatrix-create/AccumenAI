<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'patient_id' => [
                'required',
                Rule::exists('patients', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')
                ),
            ],
            'admission_id' => [
                'nullable',
                'required_if:type,ipd',
                Rule::exists('admissions', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'type' => ['required', Rule::in(['opd', 'ipd', 'pharmacy', 'lab', 'surgery'])],
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'admission_id.required_if' => 'IPD invoices require an admission.',
            'admission_id.exists' => 'Selected admission does not exist.',
            'type.required' => 'Invoice type is required.',
            'type.in' => 'Invalid invoice type selected.',
            'items.required' => 'At least one item is required.',
            'items.*.description.required' => 'Item description is required.',
            'items.*.amount.required' => 'Item amount is required.',
            'items.*.amount.min' => 'Amount must be 0 or greater.',
        ];
    }
}
