<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WbsNode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'parent_id', 'code', 'name', 'level', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WbsNode::class, 'parent_id');
    }
}
