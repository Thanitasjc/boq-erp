<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqItem extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'boq_version_id', 'wbs_id', 'cost_code_id', 'uom_id',
        'wbs_code', 'cost_code', 'item_code', 'description', 'specification', 'uom_code',
        'quantity', 'material_rate', 'labor_rate', 'equipment_rate', 'unit_rate', 'amount',
        'sort_order', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'material_rate' => 'decimal:4',
            'labor_rate' => 'decimal:4',
            'equipment_rate' => 'decimal:4',
            'unit_rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function boqVersion(): BelongsTo
    {
        return $this->belongsTo(BoqVersion::class);
    }

    public function wbs(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'wbs_id');
    }

    public function costCodeRelation(): BelongsTo
    {
        return $this->belongsTo(CostCode::class, 'cost_code_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
