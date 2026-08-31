<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\CostLedgerEntry;
use App\Models\ProgressBaseline;
use App\Models\ProgressEntry;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(
        private CostLedgerService $ledgerService,
    ) {}

    public function generateBaseline(Project $project, ?float $totalBudget = null): Collection
    {
        $budget = $totalBudget ?? (float) $project->revised_budget;
        if ($budget <= 0) {
            $budget = (float) CostLedgerEntry::where('project_id', $project->id)
                ->where('entry_type', 'budget')
                ->sum('amount');
        }

        $start = $project->start_date ?? now()->startOfMonth();
        $end = $project->end_date ?? $start->copy()->addMonths(11);
        $months = max(1, $start->diffInMonths($end) + 1);

        return DB::transaction(function () use ($project, $budget, $start, $months) {
            ProgressBaseline::where('project_id', $project->id)->delete();

            $baselines = collect();
            $cumulativePercent = 0;
            $increment = round(100 / $months, 2);

            for ($i = 0; $i < $months; $i++) {
                $period = $start->copy()->addMonths($i)->startOfMonth();
                $cumulativePercent = min(100, round($cumulativePercent + $increment, 2));
                $plannedValue = round($budget * $cumulativePercent / 100, 2);

                $baselines->push(ProgressBaseline::create([
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                    'period_month' => $period,
                    'planned_percent' => $cumulativePercent,
                    'planned_value' => $plannedValue,
                    'sort_order' => $i + 1,
                ]));
            }

            return $baselines;
        });
    }

    public function recordProgress(
        Project $project,
        string $periodMonth,
        float $actualPercent,
        ?int $costCodeId = null,
        ?string $notes = null,
    ): ProgressEntry {
        $budget = (float) $project->revised_budget;
        if ($budget <= 0) {
            $budget = (float) CostLedgerEntry::where('project_id', $project->id)
                ->where('entry_type', 'budget')
                ->sum('amount');
        }

        $earnedValue = round($budget * $actualPercent / 100, 2);

        return ProgressEntry::updateOrCreate(
            [
                'project_id' => $project->id,
                'period_month' => $periodMonth,
                'cost_code_id' => $costCodeId,
            ],
            [
                'company_id' => $project->company_id,
                'actual_percent' => $actualPercent,
                'earned_value' => $earnedValue,
                'notes' => $notes,
                'status' => 'approved',
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ],
        );
    }

    public function getProjectDashboard(Project $project): array
    {
        $ledger = $this->ledgerService->getProjectSummary($project->id);
        $scurve = $this->getScurveData($project);
        $latest = $this->getLatestMetrics($project, $scurve, $ledger);

        if ($latest['spi'] > 0 && $latest['spi'] < 0.9) {
            $this->notifyLowSpi($project, $latest['spi']);
        }

        return [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status,
                'contract_value' => (float) $project->contract_value,
                'revised_budget' => (float) $project->revised_budget,
            ],
            'kpis' => [
                'contract_value' => (float) $project->contract_value,
                'budget' => $ledger['budget'],
                'committed' => $ledger['committed'],
                'actual' => $ledger['actual'],
                'remaining' => $ledger['remaining'],
                'billing' => $ledger['billing'],
                'profit' => $ledger['profit'],
                'planned_progress' => $latest['planned_percent'],
                'actual_progress' => $latest['actual_percent'],
                'variance' => round($latest['actual_percent'] - $latest['planned_percent'], 2),
                'pv' => $latest['pv'],
                'ev' => $latest['ev'],
                'ac' => $latest['ac'],
                'spi' => $latest['spi'],
                'cpi' => $latest['cpi'],
                'eac' => $latest['eac'],
            ],
            'scurve' => $scurve,
            'ledger' => $ledger,
        ];
    }

    public function getScurveData(Project $project): array
    {
        $baselines = ProgressBaseline::where('project_id', $project->id)
            ->orderBy('period_month')
            ->get();

        if ($baselines->isEmpty()) {
            $baselines = $this->generateBaseline($project);
        }

        $entries = ProgressEntry::where('project_id', $project->id)
            ->whereNull('cost_code_id')
            ->orderBy('period_month')
            ->get()
            ->keyBy(fn ($e) => $e->period_month->format('Y-m'));

        $acByMonth = $this->getActualCostByMonth($project->id);

        $cumulativeAc = 0;
        $points = [];

        foreach ($baselines as $baseline) {
            $monthKey = $baseline->period_month->format('Y-m');
            $entry = $entries->get($monthKey);
            $cumulativeAc += (float) ($acByMonth[$monthKey] ?? 0);

            $points[] = [
                'period' => $monthKey,
                'label' => $baseline->period_month->format('M Y'),
                'planned_percent' => (float) $baseline->planned_percent,
                'planned_value' => (float) $baseline->planned_value,
                'actual_percent' => $entry ? (float) $entry->actual_percent : null,
                'earned_value' => $entry ? (float) $entry->earned_value : null,
                'actual_cost' => $cumulativeAc,
            ];
        }

        return $points;
    }

    private function getLatestMetrics(Project $project, array $scurve, array $ledger): array
    {
        $current = collect($scurve)->last(fn ($p) => $p['actual_percent'] !== null)
            ?? collect($scurve)->last();

        $pv = (float) ($current['planned_value'] ?? 0);
        $ev = (float) ($current['earned_value'] ?? 0);
        $ac = (float) ($current['actual_cost'] ?? $ledger['actual']);

        return [
            'planned_percent' => (float) ($current['planned_percent'] ?? 0),
            'actual_percent' => (float) ($current['actual_percent'] ?? 0),
            'pv' => $pv,
            'ev' => $ev,
            'ac' => $ac,
            'spi' => $pv > 0 ? round($ev / $pv, 3) : 0,
            'cpi' => $ac > 0 ? round($ev / $ac, 3) : 0,
            'eac' => ($ev > 0 && $ac > 0) ? round($ledger['budget'] / ($ev / $ac), 2) : (float) $ledger['budget'],
        ];
    }

    private function notifyLowSpi(Project $project, float $spi): void
    {
        $pmId = $project->project_manager_id;
        if (! $pmId) {
            return;
        }

        $exists = AppNotification::where('user_id', $pmId)
            ->where('type', 'low_spi')
            ->where('link', "/projects/{$project->id}/dashboard")
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        if ($exists) {
            return;
        }

        AppNotification::create([
            'company_id' => $project->company_id,
            'user_id' => $pmId,
            'type' => 'low_spi',
            'title' => 'SPI ต่ำกว่าเกณฑ์',
            'message' => "โครงการ {$project->code} มี SPI = {$spi} (< 0.9) ต้องตรวจสอบความล่าช้า",
            'link' => "/projects/{$project->id}/dashboard",
            'data' => ['project_id' => $project->id, 'spi' => $spi],
        ]);
    }

    private function getActualCostByMonth(int $projectId): \Illuminate\Support\Collection
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', entry_date)",
            'pgsql' => "to_char(entry_date, 'YYYY-MM')",
            default => "DATE_FORMAT(entry_date, '%Y-%m')",
        };

        return CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'actual')
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');
    }
}
