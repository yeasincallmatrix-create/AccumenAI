<?php

namespace App\Http\Requests;

use App\Support\BdGeo;
use App\Support\GeoHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

abstract class StudentFormRequest extends FormRequest
{
    protected function instituteId(): int
    {
        return $this->user()->institute_id;
    }

    /**
     * Unique rule scoped to the institute, ignoring soft-deleted rows and
     * (when updating) the currently edited student. The same value may still
     * exist for a student in a different institute.
     */
    private function uniqueStudentRule(string $column): Unique
    {
        return Rule::unique('students', $column)
            ->where('institute_id', $this->instituteId())
            ->whereNull('deleted_at')
            ->ignore($this->route('student'));
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'hasPermission')
            && $user->hasPermission('students.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('institute_id', $this->instituteId()),
            ],
            'gender' => ['nullable', 'in:male,female,other'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'mother_name' => ['nullable', 'string', 'max:120'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'religion' => ['nullable', 'string', 'max:40'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:100'],
            'document' => ['nullable', 'mimes:pdf,csv,svg', 'max:10240'],
            'nid_number' => ['nullable', 'string', 'regex:/^\d{10}$|^\d{13}$|^\d{15}$/', $this->uniqueStudentRule('nid_number')],
            'birth_cert_number' => ['nullable', 'string', 'max:30', $this->uniqueStudentRule('birth_cert_number')],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/', $this->uniqueStudentRule('phone')],
            'guardian_phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'email' => ['nullable', 'email', 'max:150', $this->uniqueStudentRule('email')],
            'country' => ['nullable', 'string', 'max:80', Rule::in(array_keys(config('countries')))],
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'present_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'present_admin_1_id' => ['nullable', 'integer'],
            'present_admin_2_id' => ['nullable', 'integer'],
            'present_admin_3_id' => ['nullable', 'integer'],
            'permanent_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'permanent_admin_1_id' => ['nullable', 'integer'],
            'permanent_admin_2_id' => ['nullable', 'integer'],
            'permanent_admin_3_id' => ['nullable', 'integer'],
            'present_division_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::DIVISIONS))],
            'present_district_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::DISTRICTS))],
            'present_upazila_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::UPAZILAS))],
            'present_post_office' => ['nullable', 'string', 'max:100'],
            'present_zip_code' => ['nullable', 'string', 'max:10'],
            'permanent_division_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::DIVISIONS))],
            'permanent_district_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::DISTRICTS))],
            'permanent_upazila_id' => ['nullable', 'string', 'max:10', Rule::in(array_keys(BdGeo::UPAZILAS))],
            'permanent_post_office' => ['nullable', 'string', 'max:100'],
            'permanent_zip_code' => ['nullable', 'string', 'max:10'],
            'national_id_or_birth_certificate' => ['nullable', 'string', 'max:40'],
            'passport_number' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{9}$/', $this->uniqueStudentRule('passport_number')],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'admission_date' => ['required', 'date'],
            'status' => ['required', 'in:active,completed,dropped,suspended'],
            'reg_no' => ['nullable', 'string', 'max:10', Rule::unique('students', 'reg_no')->whereNull('deleted_at')],
            'roll_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Server-side hierarchy validation for the country-neutral address fields.
     *
     * A level-2 unit must belong to the submitted level-1 unit, a level-3 unit
     * to the submitted level-2 unit, and every unit to the submitted country.
     * This blocks cross-country / cross-parent tampering on the address fields.
     */
    public function messages(): array
    {
        return [
            'nid_number.regex' => 'The NID number must be 10, 13, or 15 digits.',
            'passport_number.regex' => 'The passport number must be 9 alphanumeric characters.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach (['present_', 'permanent_'] as $prefix) {
                $countryId = $this->input($prefix.'country_id');
                $l1 = $this->input($prefix.'admin_1_id');
                $l2 = $this->input($prefix.'admin_2_id');
                $l3 = $this->input($prefix.'admin_3_id');

                // Only enforce the hierarchy when a country was actually chosen;
                // an empty address (no country) is always valid.
                if ($countryId === null || $countryId === '') {
                    continue;
                }

                $error = GeoHierarchy::validateHierarchy(
                    (int) $countryId,
                    $l1,
                    $l2,
                    $l3,
                );

                if ($error !== null) {
                    $validator->errors()->add($prefix.'admin_1_id', mawa_lang($error));
                }
            }
        });
    }
}
