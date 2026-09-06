<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TpaClaimRequest extends FormRequest
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
            'invoice_id' => [
                'required',
                Rule::exists('medical_invoices', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'tpa_company_name' => 'required|string|max:150',
            'policy_number' => 'required|string|max:50',
            'claim_amount' => 'required|numeric|min:0.01',
            'documents' => 'nullable|string',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'invoice_id.required' => 'Please select an invoice.',
            'invoice_id.exists' => 'Selected invoice does not exist.',
            'tpa_company_name.required' => 'TPA company name is required.',
            'policy_number.required' => 'Policy number is required.',
            'claim_amount.required' => 'Claim amount is required.',
            'claim_amount.min' => 'Claim amount must be at least 0.01.',
        ];
    }
}
