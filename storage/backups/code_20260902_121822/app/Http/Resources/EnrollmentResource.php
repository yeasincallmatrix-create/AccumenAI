<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'course_id' => $this->course_id,
            'batch_id' => $this->batch_id,
            'enrollment_date' => $this->enrollment_date?->toDateString(),
            'status' => $this->status ?? null,
            'student' => new StudentResource($this->whenLoaded('student')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'batch' => new BatchResource($this->whenLoaded('batch')),
        ];
    }
}
