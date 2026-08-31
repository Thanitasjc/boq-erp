<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberSequence extends Model
{
    protected $fillable = [
        'company_id', 'document_type', 'prefix', 'year', 'last_number',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
