<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDisbursementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'disbursement_date' => $this->disbursement_date?->toDateString(),
            'amount' => (float) $this->amount,
            'payee' => $this->payee,
            'description' => $this->description,
            'status' => $this->status,
            'notes' => $this->notes,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder->id,
                'document_number' => $this->purchaseOrder->document_number,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
