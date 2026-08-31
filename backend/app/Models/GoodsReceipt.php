<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'purchase_order_id', 'supplier_id',
        'document_number', 'receipt_date', 'status', 'total_amount', 'notes',
        'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'receipt_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
