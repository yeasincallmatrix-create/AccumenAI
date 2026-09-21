<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RecordCertificateReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant_id() !== null;
    }

    public function rules(): array
    {
        return [
            'party_id' => 'required|integer|exists:parties,id',
            'certificate_no' => 'required|string|max:50',
            'certificate_date' => 'required|date',
            'tax_period' => 'required|string|max:20',
            'financial_year' => 'required|string|max:20',
            'total_base' => 'required|numeric|min:0',
            'total_tds' => 'required|numeric|min:0.01',
            'attachment_path' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }
            $exists = \Illuminate\Support\Facades\DB::table('tds_certificates_received')
                ->where('institute_id', tenant_id())
                ->where('party_id', $this->party_id)
                ->where('certificate_no', $this->certificate_no)
                ->where('financial_year', $this->financial_year)
                ->exists();
            if ($exists) {
                $validator->errors()->add('certificate_no', 'A certificate with this number already exists for this party and financial year.');
            }
        });
    }
}
