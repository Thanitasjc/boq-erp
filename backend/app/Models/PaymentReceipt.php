<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'progress_claim_id',
        'document_number', 'payment_date', 'amount',
        'payment_method', 'reference_no', 'status', 'notes',
        'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function progressClaim(): BelongsTo
    {
        return $this->belongsTo(ProgressClaim::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
