<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmissionRequest extends FormRequest
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
            'bed_id' => [
                'nullable',
                Rule::exists('beds', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'admitting_doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')),
                // Phase 02: the admitting doctor must belong to this institute.
                function ($attribute, $value, $fail) use ($instituteId) {
                    if (! MedicalScope::isDoctorInInstitute((int) $value, (int) $instituteId)) {
                        $fail('Selected doctor does not belong to this institute.');
                    }
                },
            ],
            'admission_date' => 'required|date',
            'admission_time' => 'required|date_format:H:i',
            'primary_diagnosis' => 'nullable|string',
            'secondary_diagnosis' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'bed_id.exists' => 'Selected bed does not exist.',
            'admitting_doctor_id.required' => 'Please select an admitting doctor.',
            'admitting_doctor_id.exists' => 'Selected doctor does not exist.',
            'admission_date.required' => 'Admission date is required.',
            'admission_time.required' => 'Admission time is required.',
        ];
    }
}
