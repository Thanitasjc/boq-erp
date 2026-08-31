<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'cost_code_id' => $this->cost_code_id,
            'cost_code' => $this->cost_code,
            'description' => $this->description,
            'uom_code' => $this->uom_code,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
        ];
    }
}
