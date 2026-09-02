<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id_number' => $this->student_id_number,
            'reg_no' => $this->reg_no,
            'roll_number' => $this->roll_number,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'dob' => $this->dob?->toDateString(),
            'status' => $this->status,
            'admission_status' => $this->admission_status,
            'admission_date' => $this->admission_date?->toDateString(),
            'photo' => $this->photo,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'blood_group' => $this->blood_group,
            'religion' => $this->religion,
            'nationality' => $this->nationality,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'guardian_phone' => $this->guardian_phone,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'branch_id' => $this->branch_id,
            'course_id' => $this->applied_course_id,
            'academic_year_id' => $this->applied_academic_year_id,
        ];
    }
}
