<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'cost_code_id', 'cost_code', 'description',
        'uom_code', 'quantity', 'unit_price', 'amount', 'boq_item_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class);
    }
}
