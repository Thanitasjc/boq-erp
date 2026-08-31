<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\CostLedgerEntry;
use App\Models\Project;
use App\Models\VariationOrder;
use App\Models\VariationOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VariationOrderService
{
    public function __construct(
        private DocumentNumberService $docNumber,
    ) {}

    public function create(Project $project, array $data, array $items): VariationOrder
    {
        $contract = $this->resolveContract($project, $data['contract_id'] ?? null);

        return DB::transaction(function () use ($project, $contract, $data, $items) {
            $vo = VariationOrder::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'contract_id' => $contract->id,
                'document_number' => $this->docNumber->generate($project->company_id, 'variation_order', 'VO'),
                'vo_number' => $data['vo_number'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'vo_type' => $data['vo_type'] ?? 'addition',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($vo, $items);

            return $vo->fresh()->load('items', 'contract');
        });
    }

    public function update(VariationOrder $vo, array $data, array $items): VariationOrder
    {
        abort_unless($vo->isEditable(), 422, 'VO cannot be edited in current status.');

        return DB::transaction(function () use ($vo, $data, $items) {
            $vo->update([
                'title' => $data['title'] ?? $vo->title,
                'description' => $data['description'] ?? $vo->description,
                'vo_type' => $data['vo_type'] ?? $vo->vo_type,
                'vo_number' => $data['vo_number'] ?? $vo->vo_number,
                'reason' => $data['reason'] ?? $vo->reason,
                'notes' => $data['notes'] ?? $vo->notes,
            ]);

            $this->syncItems($vo, $items);

            return $vo->fresh()->load('items', 'contract');
        });
    }

    public function submit(VariationOrder $vo): VariationOrder
    {
        abort_unless($vo->status === 'draft', 422, 'Only draft VO can be submitted.');
        abort_unless($vo->items()->count() > 0, 422, 'VO must have at least one item.');

        $vo->update(['status' => 'submitted']);

        return $vo;
    }

    public function approve(VariationOrder $vo): VariationOrder
    {
        abort_unless($vo->status === 'submitted', 422, 'Only submitted VO can be approved.');

        return DB::transaction(function () use ($vo) {
            $vo->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->applyToProject($vo);
            $this->postRevisionToLedger($vo);

            return $vo->fresh()->load('items');
        });
    }

    public function reject(VariationOrder $vo, string $reason): VariationOrder
    {
        abort_unless($vo->status === 'submitted', 422, 'Only submitted VO can be rejected.');
        $vo->update(['status' => 'rejected', 'rejection_reason' => $reason]);

        return $vo;
    }

    public function getSummary(int $projectId): array
    {
        $approved = VariationOrder::where('project_id', $projectId)
            ->where('status', 'approved')
            ->get();

        $additions = $approved->where('vo_type', 'addition')->sum(fn ($v) => (float) $v->total_amount);
        $omissions = $approved->where('vo_type', 'omission')->sum(fn ($v) => (float) $v->total_amount);
        $modifications = $approved->where('vo_type', 'modification')->sum(fn ($v) => $v->signedAmount());

        return [
            'total_vos' => $approved->count(),
            'total_additions' => (float) $additions,
            'total_omissions' => (float) $omissions,
            'net_variation' => (float) $additions - (float) $omissions + (float) $modifications,
            'pending_count' => VariationOrder::where('project_id', $projectId)->where('status', 'submitted')->count(),
        ];
    }

    private function syncItems(VariationOrder $vo, array $items): void
    {
        $vo->items()->delete();
        $total = 0;
        $sortOrder = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($qty * $unitPrice, 2);
            $total += $amount;

            VariationOrderItem::create([
                'variation_order_id' => $vo->id,
                'cost_code_id' => $item['cost_code_id'] ?? null,
                'cost_code' => $item['cost_code'] ?? null,
                'description' => $item['description'],
                'uom_code' => $item['uom_code'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'boq_item_id' => $item['boq_item_id'] ?? null,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $vo->update(['total_amount' => $total]);
    }

    private function applyToProject(VariationOrder $vo): void
    {
        $signedAmount = $vo->signedAmount();
        $project = $vo->project;

        $project->update([
            'revised_budget' => max(0, (float) $project->revised_budget + $signedAmount),
        ]);

        if ($vo->contract) {
            $vo->contract->update([
                'contract_value' => max(0, (float) $vo->contract->contract_value + $signedAmount),
            ]);
            $project->update([
                'contract_value' => (float) $vo->contract->contract_value,
            ]);
        }
    }

    private function postRevisionToLedger(VariationOrder $vo): void
    {
        $multiplier = $vo->vo_type === 'omission' ? -1 : 1;

        foreach ($vo->items as $line) {
            $amount = round((float) $line->amount * $multiplier, 2);
            if ($amount == 0) {
                continue;
            }

            CostLedgerEntry::create([
                'company_id' => $vo->company_id,
                'project_id' => $vo->project_id,
                'cost_code_id' => $line->cost_code_id,
                'entry_type' => 'revision',
                'amount' => $amount,
                'running_balance' => $amount,
                'reference_type' => VariationOrder::class,
                'reference_id' => $vo->id,
                'description' => "VO {$vo->document_number} - {$line->description}",
                'entry_date' => now()->toDateString(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function resolveContract(Project $project, ?int $contractId): Contract
    {
        if ($contractId) {
            return Contract::where('project_id', $project->id)->findOrFail($contractId);
        }

        $contract = Contract::where('project_id', $project->id)->first();
        abort_unless($contract, 422, 'โครงการต้องมีสัญญาก่อนสร้าง VO');

        return $contract;
    }
}
