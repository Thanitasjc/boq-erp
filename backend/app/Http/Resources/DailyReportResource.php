<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'report_date' => $this->report_date?->toDateString(),
            'weather' => $this->weather,
            'workforce_count' => (int) $this->workforce_count,
            'summary' => $this->summary,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'is_editable' => $this->isEditable(),
            'items' => DailyReportItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
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
