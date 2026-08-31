<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashDisbursement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'purchase_order_id',
        'document_number', 'disbursement_date', 'amount',
        'payee', 'description', 'status', 'notes',
        'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'amount' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
