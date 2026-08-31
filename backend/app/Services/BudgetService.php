<?php

namespace App\Services;

use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CostLedgerEntry;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(
        private DocumentNumberService $docNumber,
    ) {}

    public function generateFromBoq(
        Project $project,
        BoqVersion $boqVersion,
        ?int $contractId = null,
        float $contingencyPercent = 0,
        float $markupPercent = 0,
        ?string $title = null,
    ): Budget {
        abort_unless($boqVersion->status === 'approved', 422, 'BOQ must be approved before generating budget.');
        abort_unless($boqVersion->project_id === $project->id, 404);

        return DB::transaction(function () use ($project, $boqVersion, $contractId, $contingencyPercent, $markupPercent, $title) {
            $boqTotal = (float) $boqVersion->total_amount;
            $contingencyAmount = round($boqTotal * $contingencyPercent / 100, 2);
            $subtotal = $boqTotal + $contingencyAmount;
            $markupAmount = round($subtotal * $markupPercent / 100, 2);
            $totalAmount = $subtotal + $markupAmount;

            $budget = Budget::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'contract_id' => $contractId,
                'boq_version_id' => $boqVersion->id,
                'document_number' => $this->docNumber->generate($project->company_id, 'budget', 'BUD'),
                'version_number' => '1.0',
                'title' => $title ?? "Budget from BOQ v{$boqVersion->version_number}",
                'status' => 'draft',
                'boq_total' => $boqTotal,
                'contingency_percent' => $contingencyPercent,
                'contingency_amount' => $contingencyAmount,
                'markup_percent' => $markupPercent,
                'markup_amount' => $markupAmount,
                'total_amount' => $totalAmount,
                'created_by' => Auth::id(),
            ]);

            $grouped = $boqVersion->items()
                ->selectRaw('cost_code_id, cost_code, SUM(amount) as total')
                ->groupBy('cost_code_id', 'cost_code')
                ->get();

            $sortOrder = 0;
            foreach ($grouped as $row) {
                $boqAmount = (float) $row->total;
                $lineContingency = round($boqAmount * $contingencyPercent / 100, 2);
                $lineSubtotal = $boqAmount + $lineContingency;
                $lineMarkup = round($lineSubtotal * $markupPercent / 100, 2);
                $budgetAmount = $lineSubtotal + $lineMarkup;

                $costCode = $row->cost_code_id
                    ? \App\Models\CostCode::find($row->cost_code_id)
                    : null;

                BudgetLine::create([
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                    'budget_id' => $budget->id,
                    'cost_code_id' => $row->cost_code_id,
                    'cost_code' => $row->cost_code,
                    'cost_code_name' => $costCode?->name,
                    'boq_amount' => $boqAmount,
                    'budget_amount' => $budgetAmount,
                    'sort_order' => ++$sortOrder,
                ]);
            }

            return $budget->load('lines', 'boqVersion', 'contract');
        });
    }

    public function approveBaseline(Budget $budget): Budget
    {
        return DB::transaction(function () use ($budget) {
            Budget::where('project_id', $budget->project_id)
                ->where('is_baseline', true)
                ->update(['is_baseline' => false]);

            $budget->update([
                'status' => 'approved',
                'is_baseline' => true,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $project = $budget->project;
            $project->update([
                'original_budget' => $budget->total_amount,
                'revised_budget' => $budget->total_amount,
                'status' => $project->status === 'planning' ? 'active' : $project->status,
            ]);

            if ($budget->contract) {
                $project->update(['contract_value' => $budget->contract->contract_value]);
            }

            $this->postBudgetToLedger($budget);

            return $budget->fresh()->load('lines');
        });
    }

    private function postBudgetToLedger(Budget $budget): void
    {
        foreach ($budget->lines as $line) {
            CostLedgerEntry::create([
                'company_id' => $budget->company_id,
                'project_id' => $budget->project_id,
                'cost_code_id' => $line->cost_code_id,
                'entry_type' => 'budget',
                'amount' => $line->budget_amount,
                'running_balance' => $line->budget_amount,
                'reference_type' => Budget::class,
                'reference_id' => $budget->id,
                'description' => "Budget baseline - {$line->cost_code}",
                'entry_date' => now()->toDateString(),
                'created_by' => Auth::id(),
            ]);
        }
    }
}
