<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    use HandlesListQueries;

    public function __construct(
        private AuditLogService $auditLog,
        private DocumentNumberService $docNumber,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::with('projectManager')
            ->where('company_id', $request->user()->company_id);

        $this->applyListFilters($query, $request, ['code', 'name', 'client_name']);

        if ($pm = $request->input('project_manager_id')) {
            $query->where('project_manager_id', $pm);
        }

        return ProjectResource::collection(
            $query->paginate($request->input('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:planning,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['code'] = $this->docNumber->generate(
            $request->user()->company_id,
            'project',
            'PRJ'
        );
        $validated['status'] ??= 'planning';

        $project = Project::create($validated);
        $this->auditLog->log('projects', 'create', $project, null, $project->toArray());

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => new ProjectResource($project->load('projectManager')),
        ], 201);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorizeCompany($project);
        $project->load('projectManager');

        return new ProjectResource($project);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeCompany($project);
        $old = $project->toArray();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', 'in:planning,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'original_budget' => ['nullable', 'numeric', 'min:0'],
            'revised_budget' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $project->update($validated);
        $this->auditLog->log('projects', 'update', $project, $old, $project->fresh()->toArray());

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => new ProjectResource($project->fresh()->load('projectManager')),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeCompany($project);
        $this->auditLog->log('projects', 'delete', $project, $project->toArray());
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    private function authorizeCompany(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }
}
