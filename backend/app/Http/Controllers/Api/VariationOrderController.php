<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VariationOrderResource;
use App\Models\Project;
use App\Models\VariationOrder;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\VariationOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariationOrderController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private VariationOrderService $voService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = VariationOrder::with('creator', 'contract')
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('vo_type')) {
            $query->where('vo_type', $type);
        }

        $vos = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => VariationOrderResource::collection($vos->items()),
            'meta' => [
                'current_page' => $vos->currentPage(),
                'last_page' => $vos->lastPage(),
                'per_page' => $vos->perPage(),
                'total' => $vos->total(),
            ],
            'summary' => $this->voService->getSummary($project->id),
        ]);
    }

    public function show(Project $project, VariationOrder $variationOrder): JsonResponse
    {
        $this->authorizeVo($project, $variationOrder);
        $variationOrder->load(['items', 'contract', 'creator', 'approvals.user']);

        return response()->json(['data' => new VariationOrderResource($variationOrder)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('vo.create'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'vo_type' => ['nullable', 'in:addition,omission,modification'],
            'vo_number' => ['nullable', 'string', 'max:30'],
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric'],
        ]);

        $vo = $this->voService->create($project, $validated, $validated['items']);
        $this->auditLog->log('vo', 'create', $vo, null, $vo->toArray(), $project->id);

        return response()->json(['data' => new VariationOrderResource($vo)], 201);
    }

    public function update(Request $request, Project $project, VariationOrder $variationOrder): JsonResponse
    {
        $this->authorizeVo($project, $variationOrder);
        abort_unless($request->user()->hasPermission('vo.create'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'vo_type' => ['sometimes', 'in:addition,omission,modification'],
            'vo_number' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required_with:items', 'numeric'],
        ]);

        $vo = $this->voService->update(
            $variationOrder,
            $validated,
            $validated['items'] ?? $variationOrder->items->toArray(),
        );

        return response()->json(['data' => new VariationOrderResource($vo)]);
    }

    public function submit(Request $request, Project $project, VariationOrder $variationOrder): JsonResponse
    {
        $this->authorizeVo($project, $variationOrder);
        $prev = $variationOrder->status;
        $vo = $this->voService->submit($variationOrder);
        $this->approvalService->record($vo, 'submit', $prev, $vo->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new VariationOrderResource($vo)]);
    }

    public function approve(Request $request, Project $project, VariationOrder $variationOrder): JsonResponse
    {
        $this->authorizeVo($project, $variationOrder);
        abort_unless($request->user()->hasPermission('vo.approve'), 403);

        $prev = $variationOrder->status;
        $vo = $this->voService->approve($variationOrder);
        $this->approvalService->record($vo, 'approve', $prev, $vo->status, $request->input('comment'), $project->id);
        $this->auditLog->log('vo', 'approve', $vo, null, null, $project->id);

        return response()->json(['data' => new VariationOrderResource($vo)]);
    }

    public function reject(Request $request, Project $project, VariationOrder $variationOrder): JsonResponse
    {
        $this->authorizeVo($project, $variationOrder);
        abort_unless($request->user()->hasPermission('vo.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $prev = $variationOrder->status;
        $vo = $this->voService->reject($variationOrder, $validated['comment']);
        $this->approvalService->record($vo, 'reject', $prev, $vo->status, $validated['comment'], $project->id);

        return response()->json(['data' => new VariationOrderResource($vo)]);
    }

    public function summary(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json(['data' => $this->voService->getSummary($project->id)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeVo(Project $project, VariationOrder $vo): void
    {
        $this->authorizeProject($project);
        abort_unless($vo->project_id === $project->id, 404);
    }
}
