<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku ?? null,
            'item_type' => $this->item_type ?? null,
            'unit' => $this->unit ?? null,
            'purchase_price' => $this->purchase_price ?? null,
            'selling_price' => $this->selling_price ?? null,
            'reorder_level' => $this->reorder_level ?? null,
            'is_active' => $this->is_active,
            'category_id' => $this->category_id ?? null,
            'branch_id' => $this->branch_id ?? null,
        ];
    }
}
