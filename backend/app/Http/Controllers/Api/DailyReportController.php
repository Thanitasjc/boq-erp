<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyReportResource;
use App\Models\DailyReport;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\DailyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private DailyReportService $reportService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $query = DailyReport::with('creator')
            ->withCount('items')
            ->where('project_id', $project->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $reports = $query->orderByDesc('report_date')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => DailyReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
            'summary' => $this->reportService->getSummary($project->id),
        ]);
    }

    public function show(Project $project, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeReport($project, $dailyReport);
        $dailyReport->load(['items', 'creator', 'approvals.user']);

        return response()->json(['data' => new DailyReportResource($dailyReport)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('site.create'), 403);

        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:30'],
            'workforce_count' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'in:material,labor,equipment'],
            'items.*.description' => ['required', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $report = $this->reportService->create($project, $validated, $validated['items']);
        $this->auditLog->log('site', 'create', $report, null, $report->toArray(), $project->id);

        return response()->json(['data' => new DailyReportResource($report)], 201);
    }

    public function update(Request $request, Project $project, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeReport($project, $dailyReport);
        abort_unless($request->user()->hasPermission('site.create'), 403);

        $validated = $request->validate([
            'report_date' => ['sometimes', 'date'],
            'weather' => ['nullable', 'string', 'max:30'],
            'workforce_count' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'in:material,labor,equipment'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'items.*.cost_code' => ['nullable', 'string', 'max:30'],
            'items.*.uom_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $report = $this->reportService->update(
            $dailyReport,
            $validated,
            $validated['items'] ?? $dailyReport->items->toArray(),
        );

        return response()->json(['data' => new DailyReportResource($report)]);
    }

    public function submit(Request $request, Project $project, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeReport($project, $dailyReport);
        $prev = $dailyReport->status;
        $report = $this->reportService->submit($dailyReport);
        $this->approvalService->record($report, 'submit', $prev, $report->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new DailyReportResource($report)]);
    }

    public function approve(Request $request, Project $project, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeReport($project, $dailyReport);
        abort_unless($request->user()->hasPermission('site.approve'), 403);

        $prev = $dailyReport->status;
        $report = $this->reportService->approve($dailyReport);
        $this->approvalService->record($report, 'approve', $prev, $report->status, $request->input('comment'), $project->id);

        return response()->json(['data' => new DailyReportResource($report)]);
    }

    public function reject(Request $request, Project $project, DailyReport $dailyReport): JsonResponse
    {
        $this->authorizeReport($project, $dailyReport);
        abort_unless($request->user()->hasPermission('site.approve'), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $prev = $dailyReport->status;
        $report = $this->reportService->reject($dailyReport, $validated['comment']);
        $this->approvalService->record($report, 'reject', $prev, $report->status, $validated['comment'], $project->id);

        return response()->json(['data' => new DailyReportResource($report)]);
    }

    public function summary(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json(['data' => $this->reportService->getSummary($project->id)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }

    private function authorizeReport(Project $project, DailyReport $report): void
    {
        $this->authorizeProject($project);
        abort_unless($report->project_id === $project->id, 404);
    }
}
