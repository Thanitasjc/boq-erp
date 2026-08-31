<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'contract_id', 'progress_entry_id',
        'document_number', 'title', 'claim_date', 'period_month',
        'progress_percent', 'previous_percent', 'gross_amount',
        'retention_percent', 'retention_amount', 'net_amount',
        'status', 'notes', 'created_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'period_month' => 'date',
            'progress_percent' => 'decimal:2',
            'previous_percent' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
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

    public function progressEntry(): BelongsTo
    {
        return $this->belongsTo(ProgressEntry::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
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
