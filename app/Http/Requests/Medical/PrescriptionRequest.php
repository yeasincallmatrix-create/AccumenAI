<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionRequest extends FormRequest
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
                    fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')->where('is_patient', true)
                ),
            ],
            'encounter_id' => [
                'nullable',
                Rule::exists('medical_encounters', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')
                ),
            ],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')),
                // Phase 02: the prescribing doctor must belong to this institute.
                function ($attribute, $value, $fail) use ($instituteId) {
                    if (! MedicalScope::isDoctorInInstitute((int) $value, (int) $instituteId)) {
                        $fail('Selected doctor does not belong to this institute.');
                    }
                },
            ],
            'prescription_date' => 'required|date',
            'diagnosis' => 'nullable|string',
            'chief_complaints' => 'nullable|string',
            'examination_findings' => 'nullable|string',
            'advice' => 'nullable|string',
            'follow_up_date' => 'nullable|date|after:prescription_date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => [
                'nullable',
                Rule::exists('medicines', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'items.*.medicine_name' => 'required|string|max:200',
            'items.*.dosage' => 'required|string|max:50',
            'items.*.frequency' => 'required|string|max:50',
            'items.*.duration_days' => 'nullable|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'doctor_id.required' => 'Please select a doctor.',
            'doctor_id.exists' => 'Selected doctor does not exist.',
            'prescription_date.required' => 'Prescription date is required.',
            'items.required' => 'At least one medicine is required.',
            'items.*.medicine_name.required' => 'Medicine name is required.',
            'items.*.dosage.required' => 'Dosage is required.',
            'items.*.frequency.required' => 'Frequency is required.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
