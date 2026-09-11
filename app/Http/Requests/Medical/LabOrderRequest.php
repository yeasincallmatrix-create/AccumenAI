<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabOrderRequest extends FormRequest
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
            'encounter_id' => [
                'nullable',
                Rule::exists('medical_encounters', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')
                ),
            ],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')),
                // Phase 02: the ordering doctor must belong to this institute.
                function ($attribute, $value, $fail) use ($instituteId) {
                    if (! MedicalScope::isDoctorInInstitute((int) $value, (int) $instituteId)) {
                        $fail('Selected doctor does not belong to this institute.');
                    }
                },
            ],
            'prescription_id' => [
                'nullable',
                Rule::exists('prescriptions', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'emergency'])],
            'order_date' => 'nullable|date',
            'clinical_notes' => 'nullable|string',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => [
                'required',
                'distinct',
                Rule::exists('lab_tests', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)->where('is_active', true)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'doctor_id.required' => 'Please select a doctor.',
            'doctor_id.exists' => 'Selected doctor does not exist.',
            'priority.required' => 'Please select a priority.',
            'priority.in' => 'Invalid priority selected.',
            'tests.required' => 'At least one test is required.',
            'tests.*.lab_test_id.distinct' => 'Duplicate tests are not allowed in one order.',
            'tests.*.lab_test_id.exists' => 'One of the selected tests does not exist.',
        ];
    }
}
