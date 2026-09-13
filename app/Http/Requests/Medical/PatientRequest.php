<?php

namespace App\Http\Requests\Medical;

use App\Models\Country;
use App\Models\Institute;
use App\Rules\PhoneRule;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Accept tenant-style DD/MM/YYYY input as well as ISO.
        if ($this->filled('date_of_birth')) {
            $normalized = mawa_parse_date($this->input('date_of_birth'));
            if ($normalized !== null) {
                $this->merge(['date_of_birth' => $normalized]);
            }
        }
        // Allow Age (+ unit) to stand in for Date of Birth: if age is given
        // and dob is empty, derive dob as today minus age in the given unit
        // (keeps existing NOT NULL DB column working without a migration).
        if (!$this->filled('date_of_birth') && $this->filled('age')) {
            $age = (int) $this->input('age');
            $unit = $this->input('age_unit', 'years');
            if (!in_array($unit, ['days', 'months', 'years'], true)) {
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
        if (!$this->filled('age_unit')) {
            $this->merge(['age_unit' => 'years']);
        }
    }

    /**
     * Country name used as the "country parameter" for phone validation.
     * Priority: selected present_country_id → institute country → Bangladesh.
     */
    public function phoneCountry(): ?string
    {
        $countryId = $this->input('present_country_id');
        if ($countryId) {
            $name = Country::whereKey($countryId)->value('name');
            if ($name) {
                return $name;
            }
        }

        $instituteId = MedicalScope::instituteId();
        if ($instituteId) {
            $instituteCountryId = Institute::whereKey($instituteId)->value('country_id');
            if ($instituteCountryId) {
                $name = Country::whereKey($instituteCountryId)->value('name');
                if ($name) {
                    return $name;
                }
            }
        }

        return 'Bangladesh';
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        $patient = $this->route('patient');
        $patientId = $patient instanceof \App\Models\Medical\Patient ? $patient->id : null;

        $country = $this->phoneCountry();

        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'age' => 'required_without:date_of_birth|nullable|integer|min:0|max:55000',
            'age_unit' => 'nullable|in:days,months,years',
            'date_of_birth' => 'required_without:age|nullable|date|before:today',
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'phone' => [
                // Shared family phones allowed (parent + child on one
                // number) — identity is mr_number, never the phone.
                'nullable',
                'string',
                'max:20',
                new PhoneRule($country),
            ],
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
            // "Whose phone is this?" — someone else's triggers guardian
            // resolution in the controller (find-or-create, never duplicate).
            'phone_owner' => 'nullable|in:self,other',
            'guardian_name' => 'required_if:phone_owner,other|nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'present_address' => 'nullable|string',
            'present_country_id' => 'nullable|exists:countries,id',
            'present_admin_1_id' => 'nullable|exists:administrative_units,id',
            'present_admin_2_id' => 'nullable|exists:administrative_units,id',
            'present_admin_3_id' => 'nullable|exists:administrative_units,id',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', new PhoneRule($country)],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'])],
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
            'age.required_without' => 'Age is required (or give Date of birth).',
            'date_of_birth.required_without' => 'Date of birth is required (or give Age).',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'blood_group.in' => 'Invalid blood group selected.',
        ];
    }
}
