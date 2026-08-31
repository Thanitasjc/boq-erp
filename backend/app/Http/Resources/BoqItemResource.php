<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoqItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wbs_code' => $this->wbs_code,
            'cost_code' => $this->cost_code,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'specification' => $this->specification,
            'uom_code' => $this->uom_code,
            'quantity' => (float) $this->quantity,
            'material_rate' => (float) $this->material_rate,
            'labor_rate' => (float) $this->labor_rate,
            'equipment_rate' => (float) $this->equipment_rate,
            'unit_rate' => (float) $this->unit_rate,
            'amount' => (float) $this->amount,
            'sort_order' => $this->sort_order,
            'remarks' => $this->remarks,
            'wbs_id' => $this->wbs_id,
            'cost_code_id' => $this->cost_code_id,
            'uom_id' => $this->uom_id,
        ];
    }
}
