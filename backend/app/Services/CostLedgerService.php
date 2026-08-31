<?php

namespace App\Services;

use App\Models\CostLedgerEntry;
use App\Models\Project;
use Illuminate\Support\Collection;

class CostLedgerService
{
    public function getProjectSummary(int $projectId): array
    {
        $budget = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'budget')
            ->sum('amount');

        $committed = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'committed')
            ->sum('amount');

        $actual = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'actual')
            ->sum('amount');

        $billing = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'billing')
            ->sum('amount');

        $cashIn = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'cash_in')
            ->sum('amount');

        $cashOut = (float) CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'cash_out')
            ->sum('amount');

        return [
            'budget' => $budget,
            'committed' => $committed,
            'actual' => $actual,
            'remaining' => $budget - $actual,
            'billing' => $billing,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'profit' => $billing - $actual,
            'margin' => $billing > 0 ? round((($billing - $actual) / $billing) * 100, 1) : 0,
        ];
    }

    public function getByCostCode(int $projectId): Collection
    {
        return CostLedgerEntry::where('project_id', $projectId)
            ->selectRaw('cost_code_id, entry_type, SUM(amount) as total')
            ->groupBy('cost_code_id', 'entry_type')
            ->with('costCode:id,code,name')
            ->get()
            ->groupBy('cost_code_id');
    }
}
