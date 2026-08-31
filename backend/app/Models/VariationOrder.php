<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VariationOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'contract_id',
        'document_number', 'vo_number', 'title', 'description',
        'vo_type', 'status', 'total_amount', 'reason', 'notes',
        'created_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VariationOrderItem::class)->orderBy('sort_order');
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

    public function signedAmount(): float
    {
        $amount = (float) $this->total_amount;

        return $this->vo_type === 'omission' ? -abs($amount) : $amount;
    }
}
