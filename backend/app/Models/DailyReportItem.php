<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportItem extends Model
{
    protected $fillable = [
        'daily_report_id', 'item_type', 'cost_code_id', 'cost_code',
        'description', 'uom_code', 'quantity', 'unit_cost', 'amount', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }
}
