<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressBaseline extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'period_month',
        'planned_percent', 'planned_value', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'planned_percent' => 'decimal:2',
            'planned_value' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
