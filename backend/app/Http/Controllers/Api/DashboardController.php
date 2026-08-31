<?php

namespace App\Http\Controllers\Api;

use App\Exports\CompanyDashboardExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\CostLedgerEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function company(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $query = $this->filteredProjectsQuery($request, $companyId);
        $projectIds = (clone $query)->pluck('id');

        $totalProjects = $projectIds->count();
        $activeProjects = (clone $query)->where('status', 'active')->count();
        $planningProjects = (clone $query)->where('status', 'planning')->count();

        $contractValue = (float) (clone $query)->sum('contract_value');
        $originalBudget = (float) (clone $query)->sum('original_budget');
        $revisedBudget = (float) (clone $query)->sum('revised_budget');

        $committedCost = (float) CostLedgerEntry::whereIn('project_id', $projectIds)
            ->where('entry_type', 'committed')->sum('amount');
        $actualCost = (float) CostLedgerEntry::whereIn('project_id', $projectIds)
            ->where('entry_type', 'actual')->sum('amount');

        $remainingBudget = $revisedBudget - $actualCost;
        $forecastCost = $actualCost + max(0, $committedCost - $actualCost);
        $forecastProfit = $contractValue - $forecastCost;
        $profitMargin = $contractValue > 0 ? round(($forecastProfit / $contractValue) * 100, 1) : 0;

        $projects = (clone $query)
            ->with('projectManager')
            ->select('id', 'code', 'name', 'status', 'contract_value', 'original_budget', 'revised_budget', 'project_manager_id', 'start_date', 'end_date')
            ->latest()
            ->limit(50)
            ->get();

        $chartData = $projects->map(function ($project) {
            $committed = (float) CostLedgerEntry::where('project_id', $project->id)
                ->where('entry_type', 'committed')->sum('amount');
            $actual = (float) CostLedgerEntry::where('project_id', $project->id)
                ->where('entry_type', 'actual')->sum('amount');
            $budget = (float) $project->revised_budget;
            $profit = (float) $project->contract_value - $actual - max(0, $committed - $actual);

            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'budget' => $budget,
                'committed' => $committed,
                'actual' => $actual,
                'contract_value' => (float) $project->contract_value,
                'profit' => $profit,
            ];
        });

        $statusChart = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count, $status) => [
                'status' => $status,
                'label' => $status,
                'count' => (int) $count,
            ])
            ->values();

        $managers = User::where('company_id', $companyId)
            ->whereIn('id', Project::where('company_id', $companyId)->whereNotNull('project_manager_id')->distinct()->pluck('project_manager_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'kpis' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'planning_projects' => $planningProjects,
                'contract_value' => $contractValue,
                'original_budget' => $originalBudget,
                'revised_budget' => $revisedBudget,
                'committed_cost' => $committedCost,
                'actual_cost' => $actualCost,
                'remaining_budget' => $remainingBudget,
                'forecast_cost' => $forecastCost,
                'forecast_profit' => $forecastProfit,
                'profit_margin' => $profitMargin,
            ],
            'projects_by_status' => (clone $query)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'chart_data' => $chartData,
            'status_chart' => $statusChart,
            'projects' => ProjectResource::collection($projects),
            'filters' => [
                'project_managers' => $managers,
                'years' => [
                    ['value' => '2569', 'label' => 'ปี 2569 (2026)', 'ce_year' => 2026],
                    ['value' => '2568', 'label' => 'ปี 2568 (2025)', 'ce_year' => 2025],
                ],
            ],
            'applied_filters' => [
                'year' => $request->input('year'),
                'status' => $request->input('status'),
                'project_manager_id' => $request->input('project_manager_id'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $companyId = $request->user()->company_id;
        $projects = $this->filteredProjectsQuery($request, $companyId)
            ->with('projectManager')
            ->get();

        $format = $request->input('format', 'xlsx');
        $filename = 'company-dashboard-'.date('Y-m-d');

        return Excel::download(
            new CompanyDashboardExport($projects),
            "{$filename}.{$format}",
        );
    }

    private function filteredProjectsQuery(Request $request, int $companyId): Builder
    {
        $query = Project::where('company_id', $companyId);

        if ($year = $request->input('year')) {
            $ceYear = is_numeric($year) && (int) $year > 2400
                ? (int) $year - 543
                : (int) $year;

            $query->where(function ($q) use ($ceYear) {
                $q->whereYear('start_date', '<=', $ceYear)
                    ->where(function ($q2) use ($ceYear) {
                        $q2->whereNull('end_date')->orWhereYear('end_date', '>=', $ceYear);
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($pmId = $request->input('project_manager_id')) {
            $query->where('project_manager_id', $pmId);
        }

        if ($projectId = $request->input('project_id')) {
            $query->where('id', $projectId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
