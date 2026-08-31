<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private ProcurementService $procurement,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = PurchaseOrder::with(['creator', 'supplier'])
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $pos = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PurchaseOrderResource::collection($pos->items()),
            'meta' => [
                'current_page' => $pos->currentPage(),
                'last_page' => $pos->lastPage(),
                'per_page' => $pos->perPage(),
                'total' => $pos->total(),
            ],
        ]);
    }

    public function show(Project $project, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorizePo($project, $purchaseOrder);

        $purchaseOrder->load(['items', 'supplier', 'purchaseRequest', 'creator', 'approvals.user']);

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('procurement.create'), 403);

        $validated = $request->validate([
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:255'],
            'order_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required_without:purchase_request_id', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        if (! empty($validated['purchase_request_id'])) {
            $pr = PurchaseRequest::findOrFail($validated['purchase_request_id']);
            $po = $this->procurement->createPurchaseOrderFromPr(
                $project,
                $pr,
                $validated['supplier_id'],
                $validated,
            );
        } else {
            $po = $this->procurement->createPurchaseOrder($project, $validated, $validated['items']);
        }

        $this->auditLog->log('procurement', 'create', $po, null, $po->toArray(), $project->id);

        return response()->json(['data' => new PurchaseOrderResource($po)], 201);
    }

    public function submit(Request $request, Project $project, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorizePo($project, $purchaseOrder);

        $prev = $purchaseOrder->status;
        $po = $this->procurement->submitPurchaseOrder($purchaseOrder);

        $this->approvalService->record($po, 'submit', $prev, $po->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new PurchaseOrderResource($po)]);
    }

    public function approve(Request $request, Project $project, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorizePo($project, $purchaseOrder);
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        $prev = $purchaseOrder->status;
        $po = $this->procurement->approvePurchaseOrder($purchaseOrder);

        $this->approvalService->record($po, 'approve', $prev, $po->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new PurchaseOrderResource($po)]);
    }

    public function issue(Request $request, Project $project, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorizePo($project, $purchaseOrder);
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        $prev = $purchaseOrder->status;
        $po = $this->procurement->issuePurchaseOrder($purchaseOrder);

        $this->approvalService->record($po, 'issue', $prev, $po->status, $request->input('comment'), $project->id);
        $this->auditLog->log('procurement', 'issue', $po, null, null, $project->id);

        return response()->json(['data' => new PurchaseOrderResource($po)]);
    }

    public function reject(Request $request, Project $project, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorizePo($project, $purchaseOrder);
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        $prev = $purchaseOrder->status;
        $po = $this->procurement->rejectPurchaseOrder($purchaseOrder, $validated['comment']);

        $this->approvalService->record($po, 'reject', $prev, $po->status, $validated['comment'], $project->id);

        return response()->json(['data' => new PurchaseOrderResource($po)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizePo(Project $project, PurchaseOrder $po): void
    {
        $this->authorizeProject($project);
        abort_unless($po->project_id === $project->id, 404);
    }
}
