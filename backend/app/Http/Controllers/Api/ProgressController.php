<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgressEntryResource;
use App\Models\AppNotification;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private ProgressService $progressService,
    ) {}

    public function dashboard(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json([
            'data' => $this->progressService->getProjectDashboard($project),
        ]);
    }

    public function scurve(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json([
            'data' => $this->progressService->getScurveData($project),
        ]);
    }

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $entries = ProgressEntry::with('creator', 'costCode')
            ->where('project_id', $project->id)
            ->orderByDesc('period_month')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ProgressEntryResource::collection($entries->items()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($request->user()->hasPermission('dashboard.project'), 403);

        $validated = $request->validate([
            'period_month' => ['required', 'date'],
            'actual_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'cost_code_id' => ['nullable', 'exists:cost_codes,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = $this->progressService->recordProgress(
            $project,
            $validated['period_month'],
            $validated['actual_percent'],
            $validated['cost_code_id'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json(['data' => new ProgressEntryResource($entry->load('creator'))], 201);
    }

    public function generateBaseline(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $baselines = $this->progressService->generateBaseline($project);

        return response()->json([
            'message' => 'สร้าง baseline S-Curve เรียบร้อย',
            'data' => $baselines,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unreadCount = AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'อ่านแล้ว']);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }
}
