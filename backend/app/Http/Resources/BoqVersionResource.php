<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoqVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'is_editable' => $this->isEditable(),
            'items_count' => $this->whenCounted('items'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
