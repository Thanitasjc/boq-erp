<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private DocumentNumberService $docNumber,
    ) {}

    public function show(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $contract = Contract::where('project_id', $project->id)->first();

        if (! $contract) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new ContractResource($contract)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $validated = $request->validate([
            'contract_number' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['required', 'numeric', 'min:0'],
            'signed_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,completed,terminated'],
        ]);

        $existing = Contract::where('project_id', $project->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Contract already exists. Use update instead.'], 422);
        }

        $contract = Contract::create([
            ...$validated,
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'document_number' => $this->docNumber->generate($project->company_id, 'contract', 'CTR'),
            'client_name' => $validated['client_name'] ?? $project->client_name,
            'status' => $validated['status'] ?? 'draft',
        ]);

        $project->update(['contract_value' => $contract->contract_value]);

        $this->auditLog->log('contracts', 'create', $contract, null, $contract->toArray(), $project->id);

        return response()->json([
            'message' => 'Contract created.',
            'data' => new ContractResource($contract),
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $contract = Contract::where('project_id', $project->id)->firstOrFail();

        $validated = $request->validate([
            'contract_number' => ['nullable', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['sometimes', 'numeric', 'min:0'],
            'signed_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active,completed,terminated'],
        ]);

        $contract->update($validated);

        if (isset($validated['contract_value'])) {
            $project->update(['contract_value' => $validated['contract_value']]);
        }

        $this->auditLog->log('contracts', 'update', $contract, null, $contract->fresh()->toArray(), $project->id);

        return response()->json([
            'message' => 'Contract updated.',
            'data' => new ContractResource($contract->fresh()),
        ]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }
}
