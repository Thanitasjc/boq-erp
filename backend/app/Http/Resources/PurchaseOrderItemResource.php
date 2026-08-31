<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_request_item_id' => $this->purchase_request_item_id,
            'cost_code_id' => $this->cost_code_id,
            'cost_code' => $this->cost_code,
            'description' => $this->description,
            'uom_code' => $this->uom_code,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'received_quantity' => (float) $this->received_quantity,
            'remaining_quantity' => $this->remainingQuantity(),
            'sort_order' => $this->sort_order,
        ];
    }
}
