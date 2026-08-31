<?php

namespace App\Services;

use App\Models\CashDisbursement;
use App\Models\Contract;
use App\Models\CostLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\ProgressClaim;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private DocumentNumberService $docNumber,
    ) {}

    public function createClaim(Project $project, array $data): ProgressClaim
    {
        $contract = $this->resolveContract($project, $data['contract_id'] ?? null);
        $previousPercent = $this->getPreviousClaimedPercent($project->id);
        $progressPercent = (float) $data['progress_percent'];
        $incrementPercent = $progressPercent - $previousPercent;

        abort_if($incrementPercent <= 0, 422, 'เปอร์เซ็นต์ต้องมากกว่าเคลมก่อนหน้า');

        $contractValue = (float) $contract->contract_value;
        $grossAmount = round($contractValue * $incrementPercent / 100, 2);

        $this->validateContractCap($project->id, $grossAmount, $contractValue);

        $retentionPercent = (float) ($data['retention_percent'] ?? $contract->retention_percent);
        $retentionAmount = round($grossAmount * $retentionPercent / 100, 2);
        $netAmount = $grossAmount - $retentionAmount;

        return ProgressClaim::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'progress_entry_id' => $data['progress_entry_id'] ?? null,
            'document_number' => $this->docNumber->generate($project->company_id, 'progress_claim', 'CLM'),
            'title' => $data['title'] ?? "เคลมงาน ณ {$progressPercent}%",
            'claim_date' => $data['claim_date'] ?? now()->toDateString(),
            'period_month' => $data['period_month'] ?? now()->startOfMonth()->toDateString(),
            'progress_percent' => $progressPercent,
            'previous_percent' => $previousPercent,
            'gross_amount' => $grossAmount,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'net_amount' => $netAmount,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
    }

    public function submitClaim(ProgressClaim $claim): ProgressClaim
    {
        abort_unless($claim->status === 'draft', 422, 'Only draft claim can be submitted.');
        $claim->update(['status' => 'submitted']);

        return $claim;
    }

    public function approveClaim(ProgressClaim $claim): ProgressClaim
    {
        abort_unless($claim->status === 'submitted', 422, 'Only submitted claim can be approved.');

        return DB::transaction(function () use ($claim) {
            $claim->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->postBillingToLedger($claim);

            return $claim;
        });
    }

    public function rejectClaim(ProgressClaim $claim, string $reason): ProgressClaim
    {
        abort_unless($claim->status === 'submitted', 422, 'Only submitted claim can be rejected.');
        $claim->update(['status' => 'rejected', 'rejection_reason' => $reason]);

        return $claim;
    }

    public function markInvoiced(ProgressClaim $claim): ProgressClaim
    {
        abort_unless($claim->status === 'approved', 422, 'Claim must be approved before invoicing.');
        $claim->update(['status' => 'invoiced']);

        return $claim;
    }

    public function createPaymentReceipt(Project $project, array $data): PaymentReceipt
    {
        $claim = null;
        if (! empty($data['progress_claim_id'])) {
            $claim = ProgressClaim::where('project_id', $project->id)
                ->findOrFail($data['progress_claim_id']);
            abort_unless(in_array($claim->status, ['approved', 'invoiced', 'paid']), 422, 'Claim must be approved before payment.');
        }

        return PaymentReceipt::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'progress_claim_id' => $claim?->id,
            'document_number' => $this->docNumber->generate($project->company_id, 'payment_receipt', 'RCP'),
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'transfer',
            'reference_no' => $data['reference_no'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
    }

    public function confirmPaymentReceipt(PaymentReceipt $receipt): PaymentReceipt
    {
        abort_unless($receipt->status === 'draft', 422, 'Only draft receipt can be confirmed.');

        return DB::transaction(function () use ($receipt) {
            $receipt->update([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            $this->postCashInToLedger($receipt);

            if ($receipt->progressClaim) {
                $receipt->progressClaim->update(['status' => 'paid']);
            }

            return $receipt;
        });
    }

    public function createDisbursement(Project $project, array $data): CashDisbursement
    {
        return CashDisbursement::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'document_number' => $this->docNumber->generate($project->company_id, 'cash_disbursement', 'DIS'),
            'disbursement_date' => $data['disbursement_date'] ?? now()->toDateString(),
            'amount' => $data['amount'],
            'payee' => $data['payee'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
    }

    public function confirmDisbursement(CashDisbursement $disbursement): CashDisbursement
    {
        abort_unless($disbursement->status === 'draft', 422, 'Only draft disbursement can be confirmed.');

        return DB::transaction(function () use ($disbursement) {
            $disbursement->update([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            $this->postCashOutToLedger($disbursement);

            return $disbursement;
        });
    }

    public function getFinanceSummary(int $projectId): array
    {
        $ledger = app(CostLedgerService::class)->getProjectSummary($projectId);

        $totalClaimed = (float) ProgressClaim::where('project_id', $projectId)
            ->whereIn('status', ['approved', 'invoiced', 'paid'])
            ->sum('gross_amount');

        $pendingClaims = (float) ProgressClaim::where('project_id', $projectId)
            ->where('status', 'submitted')
            ->sum('net_amount');

        return [
            ...$ledger,
            'total_claimed' => $totalClaimed,
            'pending_claims' => $pendingClaims,
            'margin' => $ledger['billing'] > 0
                ? round((($ledger['billing'] - $ledger['actual']) / $ledger['billing']) * 100, 1)
                : 0,
        ];
    }

    public function getCashFlow(int $projectId): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', entry_date)",
            'pgsql' => "to_char(entry_date, 'YYYY-MM')",
            default => "DATE_FORMAT(entry_date, '%Y-%m')",
        };

        $cashIn = CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'cash_in')
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $cashOut = CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'cash_out')
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $billing = CostLedgerEntry::where('project_id', $projectId)
            ->where('entry_type', 'billing')
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = collect($cashIn->keys())->merge($cashOut->keys())->merge($billing->keys())->unique()->sort()->values();

        return $months->map(fn ($month) => [
            'period' => $month,
            'cash_in' => (float) ($cashIn[$month] ?? 0),
            'cash_out' => (float) ($cashOut[$month] ?? 0),
            'billing' => (float) ($billing[$month] ?? 0),
            'net' => (float) ($cashIn[$month] ?? 0) - (float) ($cashOut[$month] ?? 0),
        ])->values()->all();
    }

    private function resolveContract(Project $project, ?int $contractId): Contract
    {
        if ($contractId) {
            return Contract::where('project_id', $project->id)->findOrFail($contractId);
        }

        $contract = Contract::where('project_id', $project->id)->first();
        abort_unless($contract, 422, 'โครงการต้องมีสัญญาก่อนสร้างเคลม');

        return $contract;
    }

    private function getPreviousClaimedPercent(int $projectId): float
    {
        return (float) ProgressClaim::where('project_id', $projectId)
            ->whereIn('status', ['approved', 'invoiced', 'paid'])
            ->max('progress_percent') ?? 0;
    }

    private function validateContractCap(int $projectId, float $newGross, float $contractValue): void
    {
        $existingGross = (float) ProgressClaim::where('project_id', $projectId)
            ->whereIn('status', ['approved', 'invoiced', 'paid', 'submitted'])
            ->sum('gross_amount');

        abort_if(
            ($existingGross + $newGross) > $contractValue,
            422,
            'มูลค่าเคลมรวมเกินมูลค่าสัญญา'
        );
    }

    private function postBillingToLedger(ProgressClaim $claim): void
    {
        CostLedgerEntry::create([
            'company_id' => $claim->company_id,
            'project_id' => $claim->project_id,
            'entry_type' => 'billing',
            'amount' => $claim->net_amount,
            'running_balance' => $claim->net_amount,
            'reference_type' => ProgressClaim::class,
            'reference_id' => $claim->id,
            'description' => "Billing - {$claim->document_number} (หัก retention {$claim->retention_percent}%)",
            'entry_date' => $claim->claim_date->toDateString(),
            'created_by' => Auth::id(),
        ]);
    }

    private function postCashInToLedger(PaymentReceipt $receipt): void
    {
        CostLedgerEntry::create([
            'company_id' => $receipt->company_id,
            'project_id' => $receipt->project_id,
            'entry_type' => 'cash_in',
            'amount' => $receipt->amount,
            'running_balance' => $receipt->amount,
            'reference_type' => PaymentReceipt::class,
            'reference_id' => $receipt->id,
            'description' => "Cash In - {$receipt->document_number}",
            'entry_date' => $receipt->payment_date->toDateString(),
            'created_by' => Auth::id(),
        ]);
    }

    private function postCashOutToLedger(CashDisbursement $disbursement): void
    {
        CostLedgerEntry::create([
            'company_id' => $disbursement->company_id,
            'project_id' => $disbursement->project_id,
            'entry_type' => 'cash_out',
            'amount' => $disbursement->amount,
            'running_balance' => $disbursement->amount,
            'reference_type' => CashDisbursement::class,
            'reference_id' => $disbursement->id,
            'description' => "Cash Out - {$disbursement->document_number} - {$disbursement->payee}",
            'entry_date' => $disbursement->disbursement_date->toDateString(),
            'created_by' => Auth::id(),
        ]);
    }
}
