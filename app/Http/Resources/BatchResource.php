<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code ?? null,
            'status' => $this->status,
            'course_id' => $this->course_id,
            'academic_year_id' => $this->academic_year_id,
            'branch_id' => $this->branch_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'capacity' => $this->capacity ?? null,
        ];
    }
}
