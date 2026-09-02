<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code ?? null,
            'address' => $this->address ?? null,
            'phone' => $this->phone ?? null,
            'email' => $this->email ?? null,
            'status' => $this->status ?? null,
            'is_principal' => $this->is_principal ?? false,
        ];
    }
}
