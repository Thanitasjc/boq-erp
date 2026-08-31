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
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'description' => $this->description,
            'uom_code' => $this->uom_code,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
