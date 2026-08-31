<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgressClaimResource;
use App\Models\ProgressClaim;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressClaimController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private FinanceService $finance,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = ProgressClaim::with('creator', 'contract')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $claims = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ProgressClaimResource::collection($claims->items()),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'last_page' => $claims->lastPage(),
                'per_page' => $claims->perPage(),
                'total' => $claims->total(),
            ],
        ]);
    }

    public function show(Project $project, ProgressClaim $progressClaim): JsonResponse
    {
        $this->authorizeClaim($project, $progressClaim);
        $progressClaim->load(['creator', 'contract', 'approvals.user']);

        return response()->json(['data' => new ProgressClaimResource($progressClaim)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('finance.create'), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'claim_date' => ['nullable', 'date'],
            'period_month' => ['nullable', 'date'],
            'progress_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'progress_entry_id' => ['nullable', 'exists:progress_entries,id'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $claim = $this->finance->createClaim($project, $validated);
        $this->auditLog->log('finance', 'create', $claim, null, $claim->toArray(), $project->id);

        return response()->json(['data' => new ProgressClaimResource($claim->load('contract'))], 201);
    }

    public function submit(Request $request, Project $project, ProgressClaim $progressClaim): JsonResponse
    {
        $this->authorizeClaim($project, $progressClaim);
        $prev = $progressClaim->status;
        $claim = $this->finance->submitClaim($progressClaim);
        $this->approvalService->record($claim, 'submit', $prev, $claim->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new ProgressClaimResource($claim)]);
    }

    public function approve(Request $request, Project $project, ProgressClaim $progressClaim): JsonResponse
    {
        $this->authorizeClaim($project, $progressClaim);
        abort_unless($request->user()->hasPermission('finance.approve'), 403);

        $prev = $progressClaim->status;
        $claim = $this->finance->approveClaim($progressClaim);
        $this->approvalService->record($claim, 'approve', $prev, $claim->status, $request->input('comment'), $project->id);
        $this->auditLog->log('finance', 'approve', $claim, null, null, $project->id);

        return response()->json(['data' => new ProgressClaimResource($claim)]);
    }

    public function reject(Request $request, Project $project, ProgressClaim $progressClaim): JsonResponse
    {
        $this->authorizeClaim($project, $progressClaim);
        abort_unless($request->user()->hasPermission('finance.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $prev = $progressClaim->status;
        $claim = $this->finance->rejectClaim($progressClaim, $validated['comment']);
        $this->approvalService->record($claim, 'reject', $prev, $claim->status, $validated['comment'], $project->id);

        return response()->json(['data' => new ProgressClaimResource($claim)]);
    }

    public function invoice(Project $project, ProgressClaim $progressClaim): JsonResponse
    {
        $this->authorizeClaim($project, $progressClaim);
        $claim = $this->finance->markInvoiced($progressClaim);

        return response()->json(['data' => new ProgressClaimResource($claim)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeClaim(Project $project, ProgressClaim $claim): void
    {
        $this->authorizeProject($project);
        abort_unless($claim->project_id === $project->id, 404);
    }
}
