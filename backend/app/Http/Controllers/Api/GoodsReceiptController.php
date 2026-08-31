<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoodsReceiptResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Services\AuditLogService;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private ProcurementService $procurement,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = GoodsReceipt::with(['creator', 'purchaseOrder', 'supplier'])
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $grs = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => GoodsReceiptResource::collection($grs->items()),
            'meta' => [
                'current_page' => $grs->currentPage(),
                'last_page' => $grs->lastPage(),
                'per_page' => $grs->perPage(),
                'total' => $grs->total(),
            ],
        ]);
    }

    public function show(Project $project, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorizeGr($project, $goodsReceipt);

        $goodsReceipt->load(['items.purchaseOrderItem', 'purchaseOrder', 'supplier', 'creator']);

        return response()->json(['data' => new GoodsReceiptResource($goodsReceipt)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('procurement.create'), 403);

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'receipt_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $po = PurchaseOrder::where('project_id', $project->id)
            ->findOrFail($validated['purchase_order_id']);

        $gr = $this->procurement->createGoodsReceipt($po, $validated, $validated['items']);

        $this->auditLog->log('procurement', 'create', $gr, null, $gr->toArray(), $project->id);

        return response()->json(['data' => new GoodsReceiptResource($gr)], 201);
    }

    public function confirm(Request $request, Project $project, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorizeGr($project, $goodsReceipt);
        abort_unless($request->user()->hasPermission('procurement.create'), 403);

        $gr = $this->procurement->confirmGoodsReceipt($goodsReceipt);

        $this->auditLog->log('procurement', 'confirm', $gr, null, null, $project->id);

        return response()->json(['data' => new GoodsReceiptResource($gr)]);
    }

    public function issuableOrders(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $orders = PurchaseOrder::with('items')
            ->where('project_id', $project->id)
            ->whereIn('status', ['issued', 'partially_received'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => PurchaseOrderResource::collection($orders)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeGr(Project $project, GoodsReceipt $gr): void
    {
        $this->authorizeProject($project);
        abort_unless($gr->project_id === $project->id, 404);
    }
}
