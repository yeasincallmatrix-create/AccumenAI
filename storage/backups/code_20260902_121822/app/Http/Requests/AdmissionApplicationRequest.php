<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Admission application intake (create + update). Institute identity comes only
 * from the authenticated user; every referenced entity (branch, course, year)
 * must belong to that institute. The application record is a students row, so
 * uniqueness rules follow the institute-scoped Student conventions.
 */
class AdmissionApplicationRequest extends FormRequest
{
    protected function instituteId(): int
    {
        return $this->user()->institute_id;
    }

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
            'full_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'regex:/^\+?\d{4,20}$/', $this->uniqueStudentRule('phone')],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'email' => ['nullable', 'email', 'max:150', $this->uniqueStudentRule('email')],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('institute_id', $this->instituteId()),
            ],
            'country' => ['nullable', 'string', 'max:80', Rule::in(array_keys(config('countries')))],
            'present_zip_code' => ['nullable', 'string', 'max:10'],
            'application_date' => ['required', 'date'],
            'admission_source' => ['nullable', 'string', 'max:60'],
            'applied_course_id' => [
                'required',
                'integer',
                Rule::exists('institute_courses', 'course_id')->where('institute_id', $this->instituteId()),
            ],
            'applied_academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where('institute_id', $this->instituteId()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must be 4 to 20 digits, optionally prefixed with +.',
            'applied_course_id.exists' => 'The selected course is not offered by this institute.',
            'applied_academic_year_id.exists' => 'The selected academic year does not belong to this institute.',
        ];
    }
}
