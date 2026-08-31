<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'title' => $this->title,
            'claim_date' => $this->claim_date?->toDateString(),
            'period_month' => $this->period_month?->toDateString(),
            'progress_percent' => (float) $this->progress_percent,
            'previous_percent' => (float) $this->previous_percent,
            'gross_amount' => (float) $this->gross_amount,
            'retention_percent' => (float) $this->retention_percent,
            'retention_amount' => (float) $this->retention_amount,
            'net_amount' => (float) $this->net_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'is_editable' => $this->isEditable(),
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
