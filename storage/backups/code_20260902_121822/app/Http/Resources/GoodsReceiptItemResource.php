<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'inventory_item_id' => $this->inventory_item_id,
            'ordered_quantity' => $this->ordered_quantity,
            'previously_received_quantity' => $this->previously_received_quantity,
            'received_quantity' => $this->received_quantity,
            'rejected_quantity' => $this->rejected_quantity,
            'unit_cost' => $this->unit_cost,
            'batch_number' => $this->batch_number,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date,
            'manufacture_date' => $this->manufacture_date,
            'serial_numbers' => $this->serial_numbers,
            'received_condition' => $this->received_condition,
            'batch_id' => $this->batch_id,
            'notes' => $this->notes,
        ];
    }
}
