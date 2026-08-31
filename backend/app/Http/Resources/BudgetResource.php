<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'status' => $this->status,
            'boq_total' => (float) $this->boq_total,
            'contingency_percent' => (float) $this->contingency_percent,
            'contingency_amount' => (float) $this->contingency_amount,
            'markup_percent' => (float) $this->markup_percent,
            'markup_amount' => (float) $this->markup_amount,
            'total_amount' => (float) $this->total_amount,
            'is_baseline' => $this->is_baseline,
            'is_editable' => $this->isEditable(),
            'notes' => $this->notes,
            'boq_version' => $this->whenLoaded('boqVersion', fn () => [
                'id' => $this->boqVersion->id,
                'version_number' => $this->boqVersion->version_number,
                'title' => $this->boqVersion->title,
            ]),
            'contract' => new ContractResource($this->whenLoaded('contract')),
            'lines' => BudgetLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
