<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostLedgerEntry extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'cost_code_id', 'wbs_id', 'boq_item_id',
        'entry_type', 'amount', 'running_balance', 'reference_type', 'reference_id',
        'description', 'entry_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class, 'cost_code_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
