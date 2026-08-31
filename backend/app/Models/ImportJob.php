<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'user_id', 'module', 'file_name', 'file_path',
        'status', 'total_rows', 'success_rows', 'failed_rows', 'warning_rows',
        'error_report_path', 'metadata', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
