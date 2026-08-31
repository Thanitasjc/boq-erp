<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressEntry extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'cost_code_id', 'period_month',
        'actual_percent', 'earned_value', 'notes', 'status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'actual_percent' => 'decimal:2',
            'earned_value' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
