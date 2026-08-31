<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    public function __construct(
        private DocumentNumberService $docNumber,
    ) {}

    public function create(Project $project, array $data, array $items): DailyReport
    {
        return DB::transaction(function () use ($project, $data, $items) {
            $report = DailyReport::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'document_number' => $this->docNumber->generate($project->company_id, 'daily_report', 'DR'),
                'report_date' => $data['report_date'],
                'weather' => $data['weather'] ?? null,
                'workforce_count' => (int) ($data['workforce_count'] ?? 0),
                'summary' => $data['summary'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($report, $items);

            return $report->fresh()->load('items');
        });
    }

    public function update(DailyReport $report, array $data, array $items): DailyReport
    {
        abort_unless($report->isEditable(), 422, 'Daily report cannot be edited in current status.');

        return DB::transaction(function () use ($report, $data, $items) {
            $report->update([
                'report_date' => $data['report_date'] ?? $report->report_date,
                'weather' => $data['weather'] ?? $report->weather,
                'workforce_count' => (int) ($data['workforce_count'] ?? $report->workforce_count),
                'summary' => $data['summary'] ?? $report->summary,
                'notes' => $data['notes'] ?? $report->notes,
            ]);

            $this->syncItems($report, $items);

            return $report->fresh()->load('items');
        });
    }

    public function submit(DailyReport $report): DailyReport
    {
        abort_unless($report->status === 'draft', 422, 'Only draft reports can be submitted.');
        $report->update(['status' => 'submitted']);

        return $report;
    }

    public function approve(DailyReport $report): DailyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only submitted reports can be approved.');
        $report->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $report;
    }

    public function reject(DailyReport $report, string $reason): DailyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only submitted reports can be rejected.');
        $report->update(['status' => 'rejected', 'rejection_reason' => $reason]);

        return $report;
    }

    public function getSummary(int $projectId): array
    {
        $approved = DailyReport::where('project_id', $projectId)->where('status', 'approved');

        return [
            'total_reports' => $approved->count(),
            'total_workforce' => (int) DailyReport::where('project_id', $projectId)->where('status', 'approved')->sum('workforce_count'),
            'total_cost' => (float) $approved->sum('total_amount'),
            'pending_count' => DailyReport::where('project_id', $projectId)->where('status', 'submitted')->count(),
        ];
    }

    private function syncItems(DailyReport $report, array $items): void
    {
        $report->items()->delete();
        $total = 0;
        $sortOrder = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $amount = round($qty * $unitCost, 2);
            $total += $amount;

            DailyReportItem::create([
                'daily_report_id' => $report->id,
                'item_type' => $item['item_type'] ?? 'material',
                'cost_code_id' => $item['cost_code_id'] ?? null,
                'cost_code' => $item['cost_code'] ?? null,
                'description' => $item['description'],
                'uom_code' => $item['uom_code'] ?? null,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'notes' => $item['notes'] ?? null,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $report->update(['total_amount' => $total]);
    }
}
