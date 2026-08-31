<?php

namespace App\Services;

use App\Models\CostLedgerEntry;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    public function __construct(
        private DocumentNumberService $docNumber,
    ) {}

    public function createPurchaseRequest(Project $project, array $data, array $items): PurchaseRequest
    {
        return DB::transaction(function () use ($project, $data, $items) {
            $pr = PurchaseRequest::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'document_number' => $this->docNumber->generate($project->company_id, 'purchase_request', 'PR'),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'required_date' => $data['required_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncPrItems($pr, $items);

            return $pr->fresh()->load('items');
        });
    }

    public function updatePurchaseRequest(PurchaseRequest $pr, array $data, array $items): PurchaseRequest
    {
        abort_unless($pr->isEditable(), 422, 'PR cannot be edited in current status.');

        return DB::transaction(function () use ($pr, $data, $items) {
            $pr->update([
                'title' => $data['title'] ?? $pr->title,
                'description' => $data['description'] ?? $pr->description,
                'required_date' => $data['required_date'] ?? $pr->required_date,
                'notes' => $data['notes'] ?? $pr->notes,
            ]);

            $this->syncPrItems($pr, $items);

            return $pr->fresh()->load('items');
        });
    }

    public function submitPurchaseRequest(PurchaseRequest $pr): PurchaseRequest
    {
        abort_unless($pr->status === 'draft', 422, 'Only draft PR can be submitted.');
        abort_unless($pr->items()->count() > 0, 422, 'PR must have at least one item.');

        $pr->update(['status' => 'submitted']);

        return $pr;
    }

    public function approvePurchaseRequest(PurchaseRequest $pr): PurchaseRequest
    {
        abort_unless($pr->status === 'submitted', 422, 'Only submitted PR can be approved.');

        $pr->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $pr;
    }

    public function rejectPurchaseRequest(PurchaseRequest $pr, string $reason): PurchaseRequest
    {
        abort_unless($pr->status === 'submitted', 422, 'Only submitted PR can be rejected.');

        $pr->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $pr;
    }

    public function createPurchaseOrderFromPr(
        Project $project,
        PurchaseRequest $pr,
        int $supplierId,
        array $data = [],
    ): PurchaseOrder {
        abort_unless($pr->status === 'approved', 422, 'PR must be approved before creating PO.');
        abort_unless($pr->project_id === $project->id, 404);

        return DB::transaction(function () use ($project, $pr, $supplierId, $data) {
            $po = PurchaseOrder::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'purchase_request_id' => $pr->id,
                'supplier_id' => $supplierId,
                'document_number' => $this->docNumber->generate($project->company_id, 'purchase_order', 'PO'),
                'title' => $data['title'] ?? "PO from {$pr->document_number}",
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'delivery_date' => $data['delivery_date'] ?? $pr->required_date,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $sortOrder = 0;
            $total = 0;
            foreach ($pr->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'purchase_request_item_id' => $item->id,
                    'cost_code_id' => $item->cost_code_id,
                    'cost_code' => $item->cost_code,
                    'description' => $item->description,
                    'uom_code' => $item->uom_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'sort_order' => ++$sortOrder,
                ]);
                $total += (float) $item->amount;
            }

            $po->update(['total_amount' => $total]);

            return $po->fresh()->load('items', 'supplier', 'purchaseRequest');
        });
    }

    public function createPurchaseOrder(Project $project, array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($project, $data, $items) {
            $po = PurchaseOrder::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'document_number' => $this->docNumber->generate($project->company_id, 'purchase_order', 'PO'),
                'title' => $data['title'],
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncPoItems($po, $items);

            return $po->fresh()->load('items', 'supplier');
        });
    }

    public function submitPurchaseOrder(PurchaseOrder $po): PurchaseOrder
    {
        abort_unless($po->status === 'draft', 422, 'Only draft PO can be submitted.');
        abort_unless($po->items()->count() > 0, 422, 'PO must have at least one item.');

        $this->checkBudget($po->project_id, $po->items);

        $po->update(['status' => 'submitted']);

        return $po;
    }

    public function approvePurchaseOrder(PurchaseOrder $po): PurchaseOrder
    {
        abort_unless($po->status === 'submitted', 422, 'Only submitted PO can be approved.');

        $this->checkBudget($po->project_id, $po->items);

        $po->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $po;
    }

    public function issuePurchaseOrder(PurchaseOrder $po): PurchaseOrder
    {
        abort_unless(in_array($po->status, ['approved', 'submitted']), 422, 'PO must be approved before issuing.');

        return DB::transaction(function () use ($po) {
            $this->checkBudget($po->project_id, $po->items);

            $po->update([
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            $this->postCommittedToLedger($po);

            return $po->fresh()->load('items');
        });
    }

    public function rejectPurchaseOrder(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        abort_unless($po->status === 'submitted', 422, 'Only submitted PO can be rejected.');

        $po->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $po;
    }

    public function createGoodsReceipt(PurchaseOrder $po, array $data, array $items): GoodsReceipt
    {
        abort_unless(in_array($po->status, ['issued', 'partially_received']), 422, 'PO must be issued before GR.');

        return DB::transaction(function () use ($po, $data, $items) {
            $gr = GoodsReceipt::create([
                'company_id' => $po->company_id,
                'project_id' => $po->project_id,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'document_number' => $this->docNumber->generate($po->company_id, 'goods_receipt', 'GR'),
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncGrItems($gr, $po, $items);

            return $gr->fresh()->load('items', 'purchaseOrder');
        });
    }

    public function confirmGoodsReceipt(GoodsReceipt $gr): GoodsReceipt
    {
        abort_unless($gr->status === 'draft', 422, 'Only draft GR can be confirmed.');
        abort_unless($gr->items()->count() > 0, 422, 'GR must have at least one item.');

        return DB::transaction(function () use ($gr) {
            $po = $gr->purchaseOrder()->with('items')->first();

            foreach ($gr->items as $grItem) {
                $poItem = $po->items->firstWhere('id', $grItem->purchase_order_item_id);
                abort_unless($poItem, 422, 'Invalid PO item reference.');

                $remaining = $poItem->remainingQuantity();
                abort_if(
                    (float) $grItem->quantity > $remaining,
                    422,
                    "GR quantity exceeds remaining PO quantity for: {$poItem->description}"
                );
            }

            $gr->update([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            $gr->load('items.purchaseOrderItem');
            $this->postActualToLedger($gr);
            $this->updatePoReceivedQuantities($po);

            return $gr->fresh()->load('items');
        });
    }

    public function checkBudget(int $projectId, Collection|array $items): void
    {
        $grouped = collect($items)->groupBy('cost_code_id');

        foreach ($grouped as $costCodeId => $lines) {
            $newAmount = $lines->sum(fn ($l) => (float) ($l->amount ?? $l['amount'] ?? 0));

            $budget = (float) CostLedgerEntry::where('project_id', $projectId)
                ->where('cost_code_id', $costCodeId)
                ->where('entry_type', 'budget')
                ->sum('amount');

            $committed = (float) CostLedgerEntry::where('project_id', $projectId)
                ->where('cost_code_id', $costCodeId)
                ->where('entry_type', 'committed')
                ->sum('amount');

            if ($budget > 0 && ($committed + $newAmount) > $budget) {
                $costCode = $lines->first()->cost_code ?? $lines->first()['cost_code'] ?? 'N/A';
                abort(422, "งบประมาณไม่เพียงพอสำหรับรหัสต้นทุน {$costCode}: งบ ฿".number_format($budget, 2)." ใช้ไปแล้ว ฿".number_format($committed, 2)." ต้องการเพิ่ม ฿".number_format($newAmount, 2));
            }
        }
    }

    private function syncPrItems(PurchaseRequest $pr, array $items): void
    {
        $pr->items()->delete();
        $total = 0;
        $sortOrder = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($qty * $unitPrice, 2);
            $total += $amount;

            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
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

        $pr->update(['total_amount' => $total]);
    }

    private function syncPoItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();
        $total = 0;
        $sortOrder = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($qty * $unitPrice, 2);
            $total += $amount;

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'purchase_request_item_id' => $item['purchase_request_item_id'] ?? null,
                'cost_code_id' => $item['cost_code_id'] ?? null,
                'cost_code' => $item['cost_code'] ?? null,
                'description' => $item['description'],
                'uom_code' => $item['uom_code'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $po->update(['total_amount' => $total]);
    }

    private function syncGrItems(GoodsReceipt $gr, PurchaseOrder $po, array $items): void
    {
        $gr->items()->delete();
        $total = 0;
        $sortOrder = 0;

        foreach ($items as $item) {
            $poItem = $po->items->firstWhere('id', $item['purchase_order_item_id']);
            abort_unless($poItem, 422, 'Invalid PO item.');

            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? $poItem->unit_price);
            $amount = round($qty * $unitPrice, 2);
            $total += $amount;

            abort_if(
                $qty > $poItem->remainingQuantity(),
                422,
                "Quantity exceeds remaining for: {$poItem->description}"
            );

            GoodsReceiptItem::create([
                'goods_receipt_id' => $gr->id,
                'purchase_order_item_id' => $poItem->id,
                'description' => $poItem->description,
                'uom_code' => $poItem->uom_code,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $gr->update(['total_amount' => $total]);
    }

    private function postCommittedToLedger(PurchaseOrder $po): void
    {
        foreach ($po->items as $line) {
            CostLedgerEntry::create([
                'company_id' => $po->company_id,
                'project_id' => $po->project_id,
                'cost_code_id' => $line->cost_code_id,
                'entry_type' => 'committed',
                'amount' => $line->amount,
                'running_balance' => $line->amount,
                'reference_type' => PurchaseOrder::class,
                'reference_id' => $po->id,
                'description' => "PO committed - {$po->document_number} - {$line->description}",
                'entry_date' => now()->toDateString(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function postActualToLedger(GoodsReceipt $gr): void
    {
        foreach ($gr->items as $line) {
            CostLedgerEntry::create([
                'company_id' => $gr->company_id,
                'project_id' => $gr->project_id,
                'cost_code_id' => $line->purchaseOrderItem->cost_code_id,
                'entry_type' => 'actual',
                'amount' => $line->amount,
                'running_balance' => $line->amount,
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $gr->id,
                'description' => "GR actual - {$gr->document_number} - {$line->description}",
                'entry_date' => $gr->receipt_date->toDateString(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function updatePoReceivedQuantities(PurchaseOrder $po): void
    {
        $po->load('items');

        foreach ($po->items as $poItem) {
            $received = GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)
                ->whereHas('goodsReceipt', fn ($q) => $q->where('status', 'confirmed'))
                ->sum('quantity');

            $poItem->update(['received_quantity' => $received]);
        }

        $allReceived = $po->items->every(fn ($i) => (float) $i->received_quantity >= (float) $i->quantity);
        $anyReceived = $po->items->some(fn ($i) => (float) $i->received_quantity > 0);

        $status = $allReceived ? 'completed' : ($anyReceived ? 'partially_received' : $po->status);
        $po->update(['status' => $status]);
    }
}
