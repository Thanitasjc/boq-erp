<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostCodeCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'cost_codes_count' => $this->whenCounted('cost_codes'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
