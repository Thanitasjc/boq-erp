<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentReceiptResource;
use App\Models\PaymentReceipt;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private FinanceService $finance,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $receipts = PaymentReceipt::with('creator', 'progressClaim')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PaymentReceiptResource::collection($receipts->items()),
            'meta' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('finance.create'), 403);

        $validated = $request->validate([
            'progress_claim_id' => ['nullable', 'exists:progress_claims,id'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt = $this->finance->createPaymentReceipt($project, $validated);
        $this->auditLog->log('finance', 'create', $receipt, null, $receipt->toArray(), $project->id);

        return response()->json(['data' => new PaymentReceiptResource($receipt->load('progressClaim'))], 201);
    }

    public function confirm(Request $request, Project $project, PaymentReceipt $paymentReceipt): JsonResponse
    {
        $this->authorizeReceipt($project, $paymentReceipt);
        $receipt = $this->finance->confirmPaymentReceipt($paymentReceipt);
        $this->auditLog->log('finance', 'confirm', $receipt, null, null, $project->id);

        return response()->json(['data' => new PaymentReceiptResource($receipt)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeReceipt(Project $project, PaymentReceipt $receipt): void
    {
        $this->authorizeProject($project);
        abort_unless($receipt->project_id === $project->id, 404);
    }
}
