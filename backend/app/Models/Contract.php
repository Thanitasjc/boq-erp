<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'document_number', 'contract_number', 'title',
        'client_name', 'contract_value', 'signed_date', 'start_date', 'end_date',
        'retention_percent', 'terms', 'status',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'signed_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
