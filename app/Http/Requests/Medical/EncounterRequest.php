<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EncounterRequest extends FormRequest
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
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')),
                // Phase 02 doctrine: active globally is not enough.
                function ($attribute, $value, $fail) use ($instituteId) {
                    if (! MedicalScope::isDoctorInInstitute((int) $value, (int) $instituteId)) {
                        $fail('Selected doctor does not belong to this institute.');
                    }
                },
            ],
            'appointment_id' => [
                'nullable',
                Rule::exists('appointments', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'admission_id' => [
                'nullable',
                'required_if:encounter_type,IPD',
                Rule::exists('admissions', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('medical_departments', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'specialty_id' => [
                'nullable',
                Rule::exists('medical_specialties', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'encounter_type' => ['required', Rule::in(['OPD', 'EMERGENCY', 'IPD', 'FOLLOW_UP', 'WALK_IN'])],
            'chief_complaint' => 'nullable|string|max:1000',
            'history_of_present_illness' => 'nullable|string|max:5000',
            'examination_notes' => 'nullable|string|max:5000',
            'assessment_notes' => 'nullable|string|max:5000',
            'plan_notes' => 'nullable|string|max:5000',
            'follow_up_notes' => 'nullable|string|max:2000',
            'diagnosis_text' => 'nullable|string|max:1000',
            'diagnosis_code' => 'nullable|string|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'doctor_id.required' => 'Please select a responsible clinician.',
            'doctor_id.exists' => 'Selected doctor does not exist.',
            'appointment_id.exists' => 'Selected appointment does not exist.',
            'admission_id.required_if' => 'IPD encounters require an admission.',
            'admission_id.exists' => 'Selected admission does not exist.',
        ];
    }
}
