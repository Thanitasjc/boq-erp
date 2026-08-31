<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        string $module,
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $projectId = null,
    ): AuditLog {
        return AuditLog::create([
            'company_id' => Auth::user()?->company_id,
            'project_id' => $projectId,
            'user_id' => Auth::id(),
            'module' => $module,
            'entity_type' => $entity ? $entity::class : 'system',
            'entity_id' => $entity?->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
