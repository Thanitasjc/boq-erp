<?php

namespace App\Http\Controllers\Api;

use App\Exports\BoqExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\BoqItemResource;
use App\Http\Resources\BoqVersionResource;
use App\Models\BoqItem;
use App\Models\BoqVersion;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\BoqCalculationService;
use App\Services\BoqImportService;
use App\Services\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoqVersionController extends Controller
{
    use HandlesListQueries;

    public function __construct(
        private AuditLogService $auditLog,
        private DocumentNumberService $docNumber,
        private BoqCalculationService $calculator,
        private BoqImportService $importService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = BoqVersion::with('creator')
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('version_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        $versions = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => BoqVersionResource::collection($versions->items()),
            'meta' => [
                'current_page' => $versions->currentPage(),
                'last_page' => $versions->lastPage(),
                'per_page' => $versions->perPage(),
                'total' => $versions->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $validated = $request->validate([
            'version_number' => ['nullable', 'string', 'max:10'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $lastVersion = BoqVersion::where('project_id', $project->id)
            ->orderByDesc('id')
            ->first();

        $versionNumber = $validated['version_number']
            ?? $this->nextVersionNumber($lastVersion?->version_number);

        $version = BoqVersion::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'document_number' => $this->docNumber->generate($project->company_id, 'boq', 'BOQ'),
            'version_number' => $versionNumber,
            'title' => $validated['title'] ?? "BOQ Version {$versionNumber}",
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->auditLog->log('boq', 'create', $version, null, $version->toArray(), $project->id);

        return response()->json([
            'message' => 'BOQ version created.',
            'data' => new BoqVersionResource($version->load('creator')),
        ], 201);
    }

    public function show(Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);

        $boqVersion->load(['creator', 'approvals.user', 'items' => fn ($q) => $q->orderBy('sort_order')]);

        return response()->json([
            'data' => new BoqVersionResource($boqVersion),
            'items' => BoqItemResource::collection($boqVersion->items),
        ]);
    }

    public function update(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'Approved BOQ cannot be edited.');

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $boqVersion->update($validated);
        $this->auditLog->log('boq', 'update', $boqVersion, null, $boqVersion->fresh()->toArray(), $project->id);

        return response()->json([
            'message' => 'BOQ version updated.',
            'data' => new BoqVersionResource($boqVersion->fresh()),
        ]);
    }

    public function destroy(Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_if($boqVersion->status === 'approved', 422, 'Cannot delete approved BOQ.');

        $this->auditLog->log('boq', 'delete', $boqVersion, $boqVersion->toArray(), null, $project->id);
        $boqVersion->delete();

        return response()->json(['message' => 'BOQ version deleted.']);
    }

    public function duplicate(Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);

        $newVersion = BoqVersion::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'document_number' => $this->docNumber->generate($project->company_id, 'boq', 'BOQ'),
            'version_number' => $this->nextVersionNumber($boqVersion->version_number),
            'title' => $boqVersion->title.' (Copy)',
            'status' => 'draft',
            'created_by' => auth()->id(),
            'notes' => $boqVersion->notes,
        ]);

        foreach ($boqVersion->items as $item) {
            $data = $item->only([
                'wbs_id', 'cost_code_id', 'uom_id', 'wbs_code', 'cost_code', 'item_code',
                'description', 'specification', 'uom_code', 'quantity',
                'material_rate', 'labor_rate', 'equipment_rate', 'unit_rate', 'amount',
                'sort_order', 'remarks',
            ]);
            $newVersion->items()->create(array_merge($data, [
                'company_id' => $project->company_id,
                'project_id' => $project->id,
            ]));
        }

        $this->calculator->recalculateVersionTotal($newVersion);
        $this->auditLog->log('boq', 'duplicate', $newVersion, null, ['from' => $boqVersion->id], $project->id);

        return response()->json([
            'message' => 'BOQ version duplicated.',
            'data' => new BoqVersionResource($newVersion->load('creator')->loadCount('items')),
        ], 201);
    }

    public function storeItem(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ is locked.');

        $validated = $request->validate([
            'wbs_code' => ['nullable', 'string', 'max:30'],
            'cost_code' => ['nullable', 'string', 'max:30'],
            'item_code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'specification' => ['nullable', 'string'],
            'uom_code' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'material_rate' => ['nullable', 'numeric', 'min:0'],
            'labor_rate' => ['nullable', 'numeric', 'min:0'],
            'equipment_rate' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $sortOrder = (int) $boqVersion->items()->max('sort_order') + 1;
        $itemData = $this->calculator->prepareItemData(
            $project->company_id,
            $project->id,
            $boqVersion->id,
            $validated,
            $sortOrder,
        );

        $item = BoqItem::create($itemData);
        $this->calculator->recalculateVersionTotal($boqVersion);

        return response()->json([
            'message' => 'Item added.',
            'data' => new BoqItemResource($item),
        ], 201);
    }

    public function updateItem(Request $request, Project $project, BoqVersion $boqVersion, BoqItem $item): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ is locked.');
        abort_unless($item->boq_version_id === $boqVersion->id, 404);

        $validated = $request->validate([
            'wbs_code' => ['nullable', 'string', 'max:30'],
            'cost_code' => ['nullable', 'string', 'max:30'],
            'item_code' => ['nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'string'],
            'specification' => ['nullable', 'string'],
            'uom_code' => ['nullable', 'string', 'max:20'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'material_rate' => ['nullable', 'numeric', 'min:0'],
            'labor_rate' => ['nullable', 'numeric', 'min:0'],
            'equipment_rate' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $itemData = $this->calculator->prepareItemData(
            $project->company_id,
            $project->id,
            $boqVersion->id,
            array_merge($item->toArray(), $validated),
            $item->sort_order,
        );

        $item->update($itemData);
        $this->calculator->recalculateVersionTotal($boqVersion);

        return response()->json([
            'message' => 'Item updated.',
            'data' => new BoqItemResource($item->fresh()),
        ]);
    }

    public function destroyItem(Project $project, BoqVersion $boqVersion, BoqItem $item): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ is locked.');
        abort_unless($item->boq_version_id === $boqVersion->id, 404);

        $item->delete();
        $this->calculator->recalculateVersionTotal($boqVersion);

        return response()->json(['message' => 'Item deleted.']);
    }

    public function submit(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ cannot be submitted.');
        abort_if($boqVersion->items()->count() === 0, 422, 'BOQ must have at least one item.');

        $previous = $boqVersion->status;
        $boqVersion->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->approvalService->record($boqVersion, 'submit', $previous, 'submitted', $request->input('comment'), $project->id);
        $this->auditLog->log('boq', 'submit', $boqVersion, null, null, $project->id);

        return response()->json([
            'message' => 'BOQ submitted for approval.',
            'data' => new BoqVersionResource($boqVersion->fresh()->load('approvals.user')),
        ]);
    }

    public function approve(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($request->user()->hasPermission('boq.approve'), 403);
        abort_if($boqVersion->status !== 'submitted', 422, 'Only submitted BOQ can be approved.');

        $previous = $boqVersion->status;
        $boqVersion->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->approvalService->record($boqVersion, 'approve', $previous, 'approved', $request->input('comment'), $project->id);
        $this->auditLog->log('boq', 'approve', $boqVersion, null, null, $project->id);

        return response()->json([
            'message' => 'BOQ approved.',
            'data' => new BoqVersionResource($boqVersion->fresh()->load('approvals.user')),
        ]);
    }

    public function reject(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($request->user()->hasPermission('boq.approve'), 403);
        abort_if($boqVersion->status !== 'submitted', 422, 'Only submitted BOQ can be rejected.');

        $validated = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $previous = $boqVersion->status;
        $boqVersion->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['comment'],
        ]);

        $this->approvalService->record($boqVersion, 'reject', $previous, 'rejected', $validated['comment'], $project->id);
        $this->auditLog->log('boq', 'reject', $boqVersion, null, null, $project->id);

        return response()->json([
            'message' => 'BOQ rejected.',
            'data' => new BoqVersionResource($boqVersion->fresh()->load('approvals.user')),
        ]);
    }

    public function importPreview(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ is locked.');

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $columnMap = $request->input('column_map')
            ? json_decode($request->input('column_map'), true)
            : null;

        $preview = $this->importService->preview($request->file('file'), $columnMap);

        return response()->json($preview);
    }

    public function importConfirm(Request $request, Project $project, BoqVersion $boqVersion): JsonResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless($boqVersion->isEditable(), 422, 'BOQ is locked.');
        abort_unless($request->user()->hasPermission('boq.import'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'column_map' => ['required', 'json'],
            'replace_existing' => ['boolean'],
        ]);

        $job = $this->importService->import(
            $boqVersion,
            $request->file('file'),
            json_decode($request->input('column_map'), true),
            $request->user()->id,
            $request->boolean('replace_existing'),
        );

        return response()->json([
            'message' => 'Import completed.',
            'data' => $job,
            'version' => new BoqVersionResource($boqVersion->fresh()->loadCount('items')),
        ]);
    }

    public function export(Project $project, BoqVersion $boqVersion): BinaryFileResponse
    {
        $this->authorizeBoq($project, $boqVersion);
        abort_unless(auth()->user()->hasPermission('boq.export'), 403);

        $filename = "BOQ_{$project->code}_v{$boqVersion->version_number}.xlsx";
        $this->auditLog->log('boq', 'export', $boqVersion, null, null, $project->id);

        return Excel::download(new BoqExport($boqVersion), $filename);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeBoq(Project $project, BoqVersion $boqVersion): void
    {
        $this->authorizeProject($project);
        abort_unless($boqVersion->project_id === $project->id, 404);
    }

    private function nextVersionNumber(?string $current): string
    {
        if (! $current) {
            return '1.0';
        }
        $parts = explode('.', $current);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);

        return $major.'.'.($minor + 1);
    }
}
