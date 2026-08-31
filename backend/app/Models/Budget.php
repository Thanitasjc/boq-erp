<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'contract_id', 'boq_version_id',
        'document_number', 'version_number', 'title', 'status',
        'boq_total', 'contingency_percent', 'contingency_amount',
        'markup_percent', 'markup_amount', 'total_amount',
        'is_baseline', 'notes', 'created_by', 'approved_by',
        'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'boq_total' => 'decimal:2',
            'contingency_percent' => 'decimal:2',
            'contingency_amount' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_baseline' => 'boolean',
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

    public function boqVersion(): BelongsTo
    {
        return $this->belongsTo(BoqVersion::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('sort_order');
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
        return in_array($this->status, ['draft', 'rejected']) && ! $this->is_baseline;
    }
}
