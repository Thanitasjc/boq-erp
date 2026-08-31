<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'contract_value' => (float) $this->contract_value,
            'original_budget' => (float) $this->original_budget,
            'revised_budget' => (float) $this->revised_budget,
            'description' => $this->description,
            'location' => $this->location,
            'project_manager' => $this->whenLoaded('projectManager', fn () => $this->projectManager ? [
                'id' => $this->projectManager->id,
                'name' => $this->projectManager->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
