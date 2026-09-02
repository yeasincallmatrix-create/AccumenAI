<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstituteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug ?? null,
            'email' => $this->email ?? null,
            'phone' => $this->phone ?? null,
            'website' => $this->website ?? null,
            'logo' => $this->logo ?? null,
            'industry' => $this->industry ?? null,
        ];
    }
}
