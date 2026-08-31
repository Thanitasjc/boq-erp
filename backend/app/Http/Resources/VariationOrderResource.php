<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariationOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'vo_number' => $this->vo_number,
            'title' => $this->title,
            'description' => $this->description,
            'vo_type' => $this->vo_type,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'signed_amount' => $this->signedAmount(),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'is_editable' => $this->isEditable(),
            'items' => VariationOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'contract' => $this->whenLoaded('contract', fn () => [
                'id' => $this->contract->id,
                'contract_number' => $this->contract->contract_number,
                'contract_value' => (float) $this->contract->contract_value,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
