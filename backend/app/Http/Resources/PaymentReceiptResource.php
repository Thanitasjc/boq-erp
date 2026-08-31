<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'notes' => $this->notes,
            'progress_claim' => $this->whenLoaded('progressClaim', fn () => [
                'id' => $this->progressClaim->id,
                'document_number' => $this->progressClaim->document_number,
                'title' => $this->progressClaim->title,
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
