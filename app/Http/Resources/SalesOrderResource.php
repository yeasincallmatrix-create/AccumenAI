<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number ?? null,
            'status' => $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
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
