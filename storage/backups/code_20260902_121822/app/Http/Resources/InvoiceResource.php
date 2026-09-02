<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'student_id' => $this->student_id,
            'type' => $this->invoice_type,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'currency_id' => $this->currency_id ?? null,
            'due_date' => $this->due_date?->toDateString(),
            'student' => new StudentResource($this->whenLoaded('student')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
