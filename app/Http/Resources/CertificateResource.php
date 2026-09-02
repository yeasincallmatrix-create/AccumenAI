<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'student_id' => $this->student_id,
            'course_id' => $this->course_id,
            'batch_id' => $this->batch_id,
            'certificate_type_id' => $this->certificate_type_id,
            'status' => $this->status,
            'issued_date' => $this->issue_date?->toDateString(),
            'student' => new StudentResource($this->whenLoaded('student')),
            'course' => new CourseResource($this->whenLoaded('course')),
        ];
    }
}
