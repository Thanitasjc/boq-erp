<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'document_number', 'report_date',
        'weather', 'workforce_count', 'summary', 'status', 'total_amount',
        'notes', 'created_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyReportItem::class)->orderBy('sort_order');
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
