<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'budget_id', 'cost_code_id',
        'cost_code', 'cost_code_name', 'boq_amount', 'budget_amount',
        'committed_amount', 'actual_amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'boq_amount' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function costCodeRelation(): BelongsTo
    {
        return $this->belongsTo(CostCode::class, 'cost_code_id');
    }
}
