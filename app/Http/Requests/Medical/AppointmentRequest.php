<?php

namespace App\Http\Requests\Medical;

use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Rules\PhoneRule;
use App\Support\MedicalScope;
use App\Support\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    /**
     * Same normalization the patient registration form uses: tenant-style
     * DD/MM/YYYY is accepted, and Age (+ unit) may stand in for Date of Birth
     * so only one of the two has to be filled in the booking popup.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('date_of_birth')) {
            $normalized = mawa_parse_date($this->input('date_of_birth'));
            if ($normalized !== null) {
                $this->merge(['date_of_birth' => $normalized]);
            }
        }

        if (! $this->filled('date_of_birth') && $this->filled('age')) {
            $age = (int) $this->input('age');
            $unit = $this->input('age_unit', 'years');
            if (! in_array($unit, ['days', 'months', 'years'], true)) {
                $unit = 'years';
            }
            if ($age >= 0) {
                $dob = match ($unit) {
                    'days' => now()->subDays($age)->toDateString(),
                    'months' => now()->subMonths($age)->toDateString(),
                    default => now()->subYears(min($age, 150))->toDateString(),
                };
                $this->merge(['date_of_birth' => $dob]);
            }
        }

        if (! $this->filled('age_unit')) {
            $this->merge(['age_unit' => 'years']);
        }
    }

    /**
     * Whether the popup is registering a brand-new patient instead of using
     * an already-selected one (no patient chosen AND the user may create).
     */
    private function isCreatingPatient(): bool
    {
        return ! $this->filled('patient_id') && $this->mayCreatePatient();
    }

    private function mayCreatePatient(): bool
    {
        $user = $this->user();

        if ($user instanceof InstituteUser) {
            return $user->hasPermission('medical_patients.create');
        }

        return Workspace::membershipFor($user)?->hasPermission('medical_patients.create') ?? false;
    }

    /**
     * Institute country drives phone validation for the inline new-patient
     * phone field (same country parameter as PatientRequest).
     */
    private function phoneCountry(): string
    {
        $instituteId = MedicalScope::instituteId();
        $countryId = $instituteId ? Institute::whereKey($instituteId)->value('country_id') : null;

        return ($countryId ? Country::whereKey($countryId)->value('name') : null)
            ?: config('locale.country.default_name', 'Bangladesh');
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();
        $creating = $this->isCreatingPatient();
        $canCreate = $this->mayCreatePatient();

        $patientExists = Rule::exists('patients', 'id')->where(
            fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')->where('is_patient', true)
        );

        return [
            // Either pick an existing patient or leave this empty and fill
            // the inline new-patient fields below — one of the two is enough.
            'patient_id' => array_merge(
                $canCreate ? ['nullable'] : ['required'],
                [$patientExists]
            ),
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

            // Inline new patient (Book Appointment popup).
            'first_name' => [Rule::requiredIf(fn () => $creating), 'nullable', 'string', 'max:50'],
            'last_name' => 'nullable|string|max:50',
            'gender' => [Rule::requiredIf(fn () => $creating), 'nullable', Rule::in(['male', 'female', 'other'])],
            'age' => [Rule::requiredIf(fn () => $creating), 'nullable', 'integer', 'min:0', 'max:150'],
            'age_unit' => 'nullable|in:days,months,years',
            'date_of_birth' => 'nullable|date|before:today',
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'])],
            'relation_to_primary' => [
                'nullable',
                Rule::in(['Self', 'Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister', 'Other']),
            ],
            'primary_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)->whereNull('deleted_at')
                ),
            ],
            // Only checked when the phone actually belongs to a new patient;
            // on an existing-patient booking the field is a lookup key only.
            'phone' => array_merge(
                ['nullable', 'string', 'max:20'],
                $creating ? [new PhoneRule($this->phoneCountry())] : []
            ),
            'present_country_id' => 'nullable|exists:countries,id',
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
            'first_name.required' => 'First name is required.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Invalid gender selected.',
            'age.required' => 'Age is required (or give Date of birth).',
            'date_of_birth.required' => 'Date of birth is required (or give Age).',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'blood_group.in' => 'Invalid blood group selected.',
            'relation_to_primary.in' => 'Invalid relation selected.',
        ];
    }
}
