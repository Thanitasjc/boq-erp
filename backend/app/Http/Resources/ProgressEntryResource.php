<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_month' => $this->period_month?->format('Y-m-d'),
            'actual_percent' => (float) $this->actual_percent,
            'earned_value' => (float) $this->earned_value,
            'notes' => $this->notes,
            'status' => $this->status,
            'cost_code' => $this->whenLoaded('costCode', fn () => [
                'id' => $this->costCode->id,
                'code' => $this->costCode->code,
                'name' => $this->costCode->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
