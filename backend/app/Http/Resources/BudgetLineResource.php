<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cost_code' => $this->cost_code,
            'cost_code_name' => $this->cost_code_name,
            'boq_amount' => (float) $this->boq_amount,
            'budget_amount' => (float) $this->budget_amount,
            'committed_amount' => (float) $this->committed_amount,
            'actual_amount' => (float) $this->actual_amount,
            'remaining' => (float) $this->budget_amount - (float) $this->actual_amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
