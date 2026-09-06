<?php

namespace App\Http\Requests\Medical;

use App\Models\Medical\Invoice;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    protected function prepareForValidation(): void
    {
        // The route carries {invoice} (bound Medical\Invoice); canonical.
        $invoice = $this->route('invoice');

        if ($invoice instanceof Invoice) {
            $this->merge(['invoice_id' => $invoice->id]);
        }
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'invoice_id' => [
                'required',
                Rule::exists('medical_invoices', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'mobile_banking', 'tpa', 'other'])],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice is required.',
            'invoice_id.exists' => 'Selected invoice does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Amount must be at least 0.01.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Invalid payment method selected.',
        ];
    }
}
