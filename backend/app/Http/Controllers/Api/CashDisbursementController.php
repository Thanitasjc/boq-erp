<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashDisbursementResource;
use App\Models\CashDisbursement;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDisbursementController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private FinanceService $finance,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $items = CashDisbursement::with('creator', 'purchaseOrder')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => CashDisbursementResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('finance.create'), 403);

        $validated = $request->validate([
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'disbursement_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = $this->finance->createDisbursement($project, $validated);
        $this->auditLog->log('finance', 'create', $item, null, $item->toArray(), $project->id);

        return response()->json(['data' => new CashDisbursementResource($item)], 201);
    }

    public function confirm(Request $request, Project $project, CashDisbursement $cashDisbursement): JsonResponse
    {
        $this->authorizeDisbursement($project, $cashDisbursement);
        $item = $this->finance->confirmDisbursement($cashDisbursement);
        $this->auditLog->log('finance', 'confirm', $item, null, null, $project->id);

        return response()->json(['data' => new CashDisbursementResource($item)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeDisbursement(Project $project, CashDisbursement $item): void
    {
        $this->authorizeProject($project);
        abort_unless($item->project_id === $project->id, 404);
    }
}
