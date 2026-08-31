<?php

namespace App\Http\Controllers\Api;

use App\Exports\BoqExport;
use App\Exports\BudgetExport;
use App\Exports\CompanyDashboardExport;
use App\Exports\CostLedgerExport;
use App\Exports\DailyReportExport;
use App\Http\Controllers\Controller;
use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\DailyReport;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['type' => 'dashboard', 'label' => 'ภาพรวมบริษัท', 'requires_project' => false, 'permission' => 'dashboard.company'],
                ['type' => 'boq', 'label' => 'รายงาน BOQ', 'requires_project' => true, 'permission' => 'boq.export'],
                ['type' => 'budget', 'label' => 'รายงานงบประมาณ', 'requires_project' => true, 'permission' => 'budget.view'],
                ['type' => 'cost', 'label' => 'รายงานต้นทุน (Ledger)', 'requires_project' => true, 'permission' => 'budget.view'],
                ['type' => 'daily_report', 'label' => 'รายงานประจำวันหน้างาน', 'requires_project' => true, 'permission' => 'site.view'],
            ],
        ]);
    }

    public function download(Request $request, string $type): BinaryFileResponse|JsonResponse
    {
        $companyId = $request->user()->company_id;

        return match ($type) {
            'dashboard' => $this->downloadDashboard($request, $companyId),
            'boq' => $this->downloadBoq($request, $companyId),
            'budget' => $this->downloadBudget($request, $companyId),
            'cost' => $this->downloadCost($request, $companyId),
            'daily_report' => $this->downloadDailyReport($request, $companyId),
            default => response()->json(['message' => 'Unknown report type'], 404),
        };
    }

    private function downloadDashboard(Request $request, int $companyId): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('dashboard.company'), 403);

        $projects = Project::where('company_id', $companyId)->with('projectManager')->get();
        $filename = 'company-dashboard-'.date('Y-m-d');

        return Excel::download(new CompanyDashboardExport($projects), "{$filename}.xlsx");
    }

    private function downloadBoq(Request $request, int $companyId): BinaryFileResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('boq.export'), 403);

        $projectId = $request->input('project_id');
        $boqId = $request->input('boq_version_id');
        abort_unless($projectId, 422, 'project_id is required');

        $query = BoqVersion::where('company_id', $companyId)->where('project_id', $projectId);
        $boq = $boqId
            ? $query->where('id', $boqId)->firstOrFail()
            : $query->where('status', 'approved')->latest()->firstOrFail();

        $boq->load('items');

        return Excel::download(
            new BoqExport($boq),
            "boq-{$boq->document_number}.xlsx",
        );
    }

    private function downloadBudget(Request $request, int $companyId): BinaryFileResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('budget.view'), 403);

        $projectId = $request->input('project_id');
        $budgetId = $request->input('budget_id');
        abort_unless($projectId, 422, 'project_id is required');

        $query = Budget::where('company_id', $companyId)->where('project_id', $projectId);
        $budget = $budgetId
            ? $query->where('id', $budgetId)->firstOrFail()
            : $query->where('status', 'approved')->latest()->firstOrFail();

        $budget->load('lines');

        return Excel::download(
            new BudgetExport($budget),
            "budget-{$budget->document_number}.xlsx",
        );
    }

    private function downloadCost(Request $request, int $companyId): BinaryFileResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('budget.view'), 403);

        $projectId = $request->input('project_id');
        abort_unless($projectId, 422, 'project_id is required');

        $project = Project::where('company_id', $companyId)->findOrFail($projectId);

        return Excel::download(
            new CostLedgerExport($project),
            "cost-ledger-{$project->code}.xlsx",
        );
    }

    private function downloadDailyReport(Request $request, int $companyId): BinaryFileResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('site.view'), 403);

        $projectId = $request->input('project_id');
        abort_unless($projectId, 422, 'project_id is required');

        $reportId = $request->input('daily_report_id');
        $query = DailyReport::where('company_id', $companyId)
            ->where('project_id', $projectId);

        $report = $reportId
            ? $query->where('id', $reportId)->with('items')->firstOrFail()
            : $query->where('status', 'approved')->latest('report_date')->with('items')->firstOrFail();

        return Excel::download(
            new DailyReportExport($report),
            "daily-report-{$report->document_number}.xlsx",
        );
    }
}
