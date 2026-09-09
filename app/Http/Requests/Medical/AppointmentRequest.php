<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
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
                // Phase 02: active globally is not enough — the account must
                // belong to this institute (membership or Doctor profile).
                function ($attribute, $value, $fail) use ($instituteId) {
                    if (! MedicalScope::isDoctorInInstitute((int) $value, (int) $instituteId)) {
                        $fail('Selected doctor does not belong to this institute.');
                    }
                },
            ],
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'complaints' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'doctor_id.required' => 'Please select a doctor.',
            'doctor_id.exists' => 'Selected doctor does not exist.',
            'appointment_date.required' => 'Appointment date is required.',
            'appointment_date.after_or_equal' => 'Appointment date cannot be in the past.',
            'appointment_time.required' => 'Appointment time is required.',
        ];
    }
}
