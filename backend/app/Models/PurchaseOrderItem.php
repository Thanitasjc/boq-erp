<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'purchase_request_item_id', 'cost_code_id', 'cost_code',
        'description', 'uom_code', 'quantity', 'unit_price', 'amount',
        'received_quantity', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'received_quantity' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
