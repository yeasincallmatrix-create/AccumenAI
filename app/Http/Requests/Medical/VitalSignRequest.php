<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'admission_id' => [
                'nullable',
                'required_without_all:appointment_id,patient_id',
                Rule::exists('admissions', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'appointment_id' => [
                'nullable',
                'required_without_all:admission_id,patient_id',
                Rule::exists('appointments', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'patient_id' => [
                'nullable',
                Rule::exists('patients', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'temperature' => 'nullable|numeric|between:35,42',
            'blood_pressure_systolic' => 'nullable|integer|min:60|max:250',
            'blood_pressure_diastolic' => 'nullable|integer|min:30|max:150',
            'pulse' => 'nullable|integer|min:30|max:250',
            'heart_rate' => 'nullable|integer|min:30|max:250',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'spo2' => 'nullable|integer|min:70|max:100',
            'pain_score' => 'nullable|integer|min:0|max:10',
            'blood_sugar' => 'nullable|numeric|min:20|max:500',
            'weight' => 'nullable|numeric|min:1|max:300',
            'height' => 'nullable|numeric|min:30|max:250',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
                'admission_id.required_without_all' => 'Please select an admission or an appointment.',
                'admission_id.exists' => 'Selected admission does not exist.',
                'appointment_id.required_without_all' => 'Please select an admission or an appointment.',
                'appointment_id.exists' => 'Selected appointment does not exist.',
                'patient_id.exists' => 'Selected patient does not exist.',
            'temperature.between' => 'Temperature must be between 35 and 42 °C.',
            'blood_pressure_systolic.min' => 'Systolic BP must be at least 60.',
            'blood_pressure_systolic.max' => 'Systolic BP must be at most 250.',
            'blood_pressure_diastolic.min' => 'Diastolic BP must be at least 30.',
            'blood_pressure_diastolic.max' => 'Diastolic BP must be at most 150.',
            'pulse.min' => 'Pulse must be at least 30.',
            'pulse.max' => 'Pulse must be at most 250.',
            'spo2.min' => 'SpO2 must be at least 70.',
            'spo2.max' => 'SpO2 must be at most 100.',
        ];
    }
}
