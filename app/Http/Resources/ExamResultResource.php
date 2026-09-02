<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_id' => $this->student_id,
            'marks_obtained' => $this->marks_obtained,
            'written_marks' => $this->written_marks,
            'practical_marks' => $this->practical_marks,
            'viva_marks' => $this->viva_marks,
            'grade' => $this->grade ?? null,
            'gpa' => $this->gpa ?? null,
            'result_status' => $this->result_status ?? null,
            'remarks' => $this->remarks ?? null,
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
