<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'batch_id' => $this->batch_id,
            'date' => $this->class_date?->toDateString(),
            'status' => $this->status,
            'marked_by' => $this->marked_by ?? null,
            'student' => new StudentResource($this->whenLoaded('student')),
            'batch' => new BatchResource($this->whenLoaded('batch')),
        ];
    }
}
