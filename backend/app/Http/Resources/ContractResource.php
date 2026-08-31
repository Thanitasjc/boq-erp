<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'contract_number' => $this->contract_number,
            'title' => $this->title,
            'client_name' => $this->client_name,
            'contract_value' => (float) $this->contract_value,
            'signed_date' => $this->signed_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'retention_percent' => (float) $this->retention_percent,
            'terms' => $this->terms,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
