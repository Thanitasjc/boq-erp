<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'title' => $this->title,
            'order_date' => $this->order_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'is_editable' => $this->isEditable(),
            'purchase_request' => $this->whenLoaded('purchaseRequest', fn () => [
                'id' => $this->purchaseRequest->id,
                'document_number' => $this->purchaseRequest->document_number,
                'title' => $this->purchaseRequest->title,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ]),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
