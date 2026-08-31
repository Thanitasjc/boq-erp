<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'purchase_request_id', 'supplier_id',
        'document_number', 'title', 'order_date', 'delivery_date', 'status',
        'total_amount', 'notes', 'created_by', 'approved_by', 'approved_at',
        'issued_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'order_date' => 'date',
            'delivery_date' => 'date',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }
}
