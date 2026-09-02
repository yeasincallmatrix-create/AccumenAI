<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code ?? null,
            'description' => $this->description ?? null,
            'status' => $this->status ?? null,
            'category_id' => $this->course_category_id ?? null,
            'duration' => $this->duration ?? null,
        ];
    }
}
