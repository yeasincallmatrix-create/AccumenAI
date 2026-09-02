<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends StudentFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Student number is generated on creation and then immutable.
        unset($rules['student_id_number']);

        // Allow keeping the current reg_no.
        $rules['reg_no'] = [
            'nullable', 'string', 'max:10',
            Rule::unique('students', 'reg_no')
                ->ignore($this->route('student'))
                ->whereNull('deleted_at'),
        ];

        return $rules;
    }
}
