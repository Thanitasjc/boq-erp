<?php

namespace App\Services;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalService
{
    public function record(
        Model $approvable,
        string $action,
        string $previousStatus,
        string $newStatus,
        ?string $comment = null,
        ?int $projectId = null,
    ): Approval {
        $user = Auth::user();

        return Approval::create([
            'company_id' => $user->company_id,
            'project_id' => $projectId,
            'approvable_type' => $approvable::class,
            'approvable_id' => $approvable->id,
            'user_id' => $user->id,
            'role' => $user->roles->first()?->name,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
        ]);
    }
}
