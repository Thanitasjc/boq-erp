<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'client_name', 'project_manager_id',
        'status', 'start_date', 'end_date', 'contract_value',
        'original_budget', 'revised_budget', 'description', 'location',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'contract_value' => 'decimal:2',
            'original_budget' => 'decimal:2',
            'revised_budget' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function boqVersions(): HasMany
    {
        return $this->hasMany(BoqVersion::class);
    }
}
