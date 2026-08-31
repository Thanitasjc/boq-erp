<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private ProcurementService $procurement,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = PurchaseRequest::with('creator')
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $prs = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PurchaseRequestResource::collection($prs->items()),
            'meta' => [
                'current_page' => $prs->currentPage(),
                'last_page' => $prs->lastPage(),
                'per_page' => $prs->perPage(),
                'total' => $prs->total(),
            ],
        ]);
    }

    public function show(Project $project, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizePr($project, $purchaseRequest);

        $purchaseRequest->load(['items', 'creator', 'approvals.user']);

        return response()->json(['data' => new PurchaseRequestResource($purchaseRequest)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('procurement.create'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'required_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $pr = $this->procurement->createPurchaseRequest($project, $validated, $validated['items']);

        $this->auditLog->log('procurement', 'create', $pr, null, $pr->toArray(), $project->id);

        return response()->json(['data' => new PurchaseRequestResource($pr)], 201);
    }

    public function update(Request $request, Project $project, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizePr($project, $purchaseRequest);
        abort_unless($request->user()->hasPermission('procurement.create'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'required_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $pr = $this->procurement->updatePurchaseRequest(
            $purchaseRequest,
            $validated,
            $validated['items'] ?? $purchaseRequest->items->toArray(),
        );

        return response()->json(['data' => new PurchaseRequestResource($pr)]);
    }

    public function submit(Request $request, Project $project, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizePr($project, $purchaseRequest);

        $prev = $purchaseRequest->status;
        $pr = $this->procurement->submitPurchaseRequest($purchaseRequest);

        $this->approvalService->record($pr, 'submit', $prev, $pr->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new PurchaseRequestResource($pr)]);
    }

    public function approve(Request $request, Project $project, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizePr($project, $purchaseRequest);
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        $prev = $purchaseRequest->status;
        $pr = $this->procurement->approvePurchaseRequest($purchaseRequest);

        $this->approvalService->record($pr, 'approve', $prev, $pr->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new PurchaseRequestResource($pr)]);
    }

    public function reject(Request $request, Project $project, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizePr($project, $purchaseRequest);
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        $prev = $purchaseRequest->status;
        $pr = $this->procurement->rejectPurchaseRequest($purchaseRequest, $validated['comment']);

        $this->approvalService->record($pr, 'reject', $prev, $pr->status, $validated['comment'], $project->id);

        return response()->json(['data' => new PurchaseRequestResource($pr)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizePr(Project $project, PurchaseRequest $pr): void
    {
        $this->authorizeProject($project);
        abort_unless($pr->project_id === $project->id, 404);
    }
}
