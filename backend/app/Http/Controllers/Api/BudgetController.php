<?php

namespace App\Http\Controllers\Api;

use App\Exports\BudgetExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\BudgetService;
use App\Services\CostLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BudgetController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private BudgetService $budgetService,
        private ApprovalService $approvalService,
        private CostLedgerService $ledgerService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $budgets = Budget::with('creator', 'boqVersion')
            ->withCount('lines')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => BudgetResource::collection($budgets->items()),
            'meta' => [
                'current_page' => $budgets->currentPage(),
                'last_page' => $budgets->lastPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total(),
            ],
        ]);
    }

    public function show(Project $project, Budget $budget): JsonResponse
    {
        $this->authorizeBudget($project, $budget);

        $budget->load(['lines', 'boqVersion', 'contract', 'creator', 'approvals.user']);

        return response()->json([
            'data' => new BudgetResource($budget),
            'ledger_summary' => $this->ledgerService->getProjectSummary($project->id),
        ]);
    }

    public function generate(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('budget.create') || $request->user()->hasPermission('boq.edit'), 403);

        $validated = $request->validate([
            'boq_version_id' => ['required', 'exists:boq_versions,id'],
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'contingency_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $boqVersion = BoqVersion::findOrFail($validated['boq_version_id']);
        $contractId = $validated['contract_id']
            ?? Contract::where('project_id', $project->id)->value('id');

        $budget = $this->budgetService->generateFromBoq(
            $project,
            $boqVersion,
            $contractId,
            $validated['contingency_percent'] ?? 0,
            $validated['markup_percent'] ?? 0,
            $validated['title'] ?? null,
        );

        $this->auditLog->log('budget', 'generate', $budget, null, $budget->toArray(), $project->id);

        return response()->json([
            'message' => 'Budget generated from BOQ.',
            'data' => new BudgetResource($budget),
        ], 201);
    }

    public function submit(Request $request, Project $project, Budget $budget): JsonResponse
    {
        $this->authorizeBudget($project, $budget);
        abort_unless($budget->isEditable(), 422, 'Budget cannot be submitted.');

        $previous = $budget->status;
        $budget->update(['status' => 'submitted']);

        $this->approvalService->record($budget, 'submit', $previous, 'submitted', $request->input('comment'), $project->id);
        $this->auditLog->log('budget', 'submit', $budget, null, null, $project->id);

        return response()->json([
            'message' => 'Budget submitted for approval.',
            'data' => new BudgetResource($budget->fresh()->load('approvals.user')),
        ]);
    }

    public function approve(Request $request, Project $project, Budget $budget): JsonResponse
    {
        $this->authorizeBudget($project, $budget);
        abort_unless($request->user()->hasPermission('budget.approve'), 403);
        abort_if($budget->status !== 'submitted', 422, 'Only submitted budgets can be approved.');

        $previous = $budget->status;
        $budget = $this->budgetService->approveBaseline($budget);

        $this->approvalService->record($budget, 'approve', $previous, 'approved', $request->input('comment'), $project->id);
        $this->auditLog->log('budget', 'approve', $budget, null, null, $project->id);

        return response()->json([
            'message' => 'Budget approved and baseline locked.',
            'data' => new BudgetResource($budget->load('lines', 'approvals.user')),
        ]);
    }

    public function reject(Request $request, Project $project, Budget $budget): JsonResponse
    {
        $this->authorizeBudget($project, $budget);
        abort_unless($request->user()->hasPermission('budget.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string']]);

        $previous = $budget->status;
        $budget->update(['status' => 'rejected', 'rejection_reason' => $validated['comment']]);

        $this->approvalService->record($budget, 'reject', $previous, 'rejected', $validated['comment'], $project->id);

        return response()->json([
            'message' => 'Budget rejected.',
            'data' => new BudgetResource($budget->fresh()),
        ]);
    }

    public function export(Project $project, Budget $budget): BinaryFileResponse
    {
        $this->authorizeBudget($project, $budget);
        $budget->load('lines');

        return Excel::download(
            new BudgetExport($budget),
            "Budget_{$project->code}_v{$budget->version_number}.xlsx"
        );
    }

    public function ledger(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json([
            'summary' => $this->ledgerService->getProjectSummary($project->id),
            'by_cost_code' => $this->ledgerService->getByCostCode($project->id),
        ]);
    }

    public function approvedBoqVersions(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $versions = BoqVersion::where('project_id', $project->id)
            ->where('status', 'approved')
            ->select('id', 'version_number', 'title', 'total_amount', 'document_number')
            ->get();

        return response()->json(['data' => $versions]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeBudget(Project $project, Budget $budget): void
    {
        $this->authorizeProject($project);
        abort_unless($budget->project_id === $project->id, 404);
    }
}
