<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'email' => $this->email ?? null,
            'phone' => $this->phone ?? null,
            'status_id' => $this->status_id,
            'source_id' => $this->source_id ?? null,
            'organization_id' => $this->organization_id ?? null,
            'contact_id' => $this->contact_id ?? null,
            'assigned_user_id' => $this->assigned_user_id ?? null,
            'status' => $this->whenLoaded('status', fn () => $this->status->name),
            'branch_id' => $this->branch_id,
        ];
    }
}
