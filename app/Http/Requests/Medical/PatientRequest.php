<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        $patient = $this->route('patient');
        $patientId = $patient instanceof \App\Models\Medical\Patient ? $patient->id : null;

        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('patients', 'phone')
                    ->ignore($patientId)
                    ->where(fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')),
            ],
            'email' => 'nullable|email|max:100',
            'present_address' => 'nullable|string',
            'present_country_id' => 'nullable|exists:countries,id',
            'present_admin_1_id' => 'nullable|exists:administrative_units,id',
            'present_admin_2_id' => 'nullable|exists:administrative_units,id',
            'present_admin_3_id' => 'nullable|exists:administrative_units,id',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'allergies' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'phone.unique' => 'A patient with this phone number already exists.',
            'blood_group.in' => 'Invalid blood group selected.',
        ];
    }
}
