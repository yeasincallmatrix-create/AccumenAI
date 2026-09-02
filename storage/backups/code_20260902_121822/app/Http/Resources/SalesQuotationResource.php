<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesQuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quotation_number' => $this->quotation_number ?? null,
            'status' => $this->status,
            'quotation_date' => $this->quotation_date?->toDateString(),
            'validity_date' => $this->validity_date?->toDateString(),
            'customer_id' => $this->customer_id ?? null,
            'currency_id' => $this->currency_id ?? null,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount ?? null,
            'tax_amount' => $this->tax_amount ?? null,
            'grand_total' => $this->grand_total,
            'notes' => $this->notes ?? null,
        ];
    }
}
