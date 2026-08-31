<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder->id,
                'document_number' => $this->purchaseOrder->document_number,
                'title' => $this->purchaseOrder->title,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'items' => GoodsReceiptItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
