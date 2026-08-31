<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCodeCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_en', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function costCodes(): HasMany
    {
        return $this->hasMany(CostCode::class, 'category', 'code');
    }
}
