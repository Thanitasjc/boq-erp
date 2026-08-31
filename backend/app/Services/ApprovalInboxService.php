<?php

namespace App\Services;

use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\DailyReport;
use App\Models\ProgressClaim;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\VariationOrder;
use Illuminate\Support\Collection;

class ApprovalInboxService
{
    public function pending(int $companyId, ?string $type = null): Collection
    {
        $items = collect();

        if (! $type || $type === 'boq') {
            $items = $items->concat($this->mapBoq($companyId));
        }
        if (! $type || $type === 'budget') {
            $items = $items->concat($this->mapBudget($companyId));
        }
        if (! $type || $type === 'pr') {
            $items = $items->concat($this->mapPr($companyId));
        }
        if (! $type || $type === 'po') {
            $items = $items->concat($this->mapPo($companyId));
        }
        if (! $type || $type === 'claim') {
            $items = $items->concat($this->mapClaim($companyId));
        }
        if (! $type || $type === 'vo') {
            $items = $items->concat($this->mapVo($companyId));
        }
        if (! $type || $type === 'daily_report') {
            $items = $items->concat($this->mapDailyReport($companyId));
        }

        return $items->sortByDesc('submitted_at')->values();
    }

    public function count(int $companyId): int
    {
        return $this->pending($companyId)->count();
    }

    private function mapBoq(int $companyId): Collection
    {
        return BoqVersion::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($b) => $this->item(
                'boq', $b->id, $b->project_id,
                $b->project?->code, $b->project?->name,
                $b->document_number, $b->title,
                (float) $b->total_amount,
                $b->submitted_at ?? $b->updated_at,
                'boq.approve',
                "/projects/{$b->project_id}/boq/{$b->id}",
            ));
    }

    private function mapBudget(int $companyId): Collection
    {
        return Budget::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($b) => $this->item(
                'budget', $b->id, $b->project_id,
                $b->project?->code, $b->project?->name,
                $b->document_number, $b->title,
                (float) $b->total_amount,
                $b->updated_at,
                'budget.approve',
                "/projects/{$b->project_id}/budget/{$b->id}",
            ));
    }

    private function mapPr(int $companyId): Collection
    {
        return PurchaseRequest::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($p) => $this->item(
                'pr', $p->id, $p->project_id,
                $p->project?->code, $p->project?->name,
                $p->document_number, $p->title,
                (float) $p->total_amount,
                $p->updated_at,
                'procurement.approve',
                "/projects/{$p->project_id}/pr",
            ));
    }

    private function mapPo(int $companyId): Collection
    {
        return PurchaseOrder::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($p) => $this->item(
                'po', $p->id, $p->project_id,
                $p->project?->code, $p->project?->name,
                $p->document_number, $p->title,
                (float) $p->total_amount,
                $p->updated_at,
                'procurement.approve',
                "/projects/{$p->project_id}/po",
            ));
    }

    private function mapClaim(int $companyId): Collection
    {
        return ProgressClaim::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($c) => $this->item(
                'claim', $c->id, $c->project_id,
                $c->project?->code, $c->project?->name,
                $c->document_number, $c->title,
                (float) $c->net_amount,
                $c->updated_at,
                'finance.approve',
                "/projects/{$c->project_id}/billing",
            ));
    }

    private function mapVo(int $companyId): Collection
    {
        return VariationOrder::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($v) => $this->item(
                'vo', $v->id, $v->project_id,
                $v->project?->code, $v->project?->name,
                $v->document_number, $v->title,
                (float) $v->total_amount,
                $v->updated_at,
                'vo.approve',
                "/projects/{$v->project_id}/vo",
            ));
    }

    private function mapDailyReport(int $companyId): Collection
    {
        return DailyReport::where('company_id', $companyId)
            ->where('status', 'submitted')
            ->with('project:id,code,name')
            ->get()
            ->map(fn ($d) => $this->item(
                'daily_report', $d->id, $d->project_id,
                $d->project?->code, $d->project?->name,
                $d->document_number, $d->summary ?? "รายงาน {$d->report_date?->format('d/m/Y')}",
                (float) $d->total_amount,
                $d->updated_at,
                'site.approve',
                "/projects/{$d->project_id}/daily-report",
            ));
    }

    private function item(
        string $type,
        int $id,
        int $projectId,
        ?string $projectCode,
        ?string $projectName,
        ?string $documentNumber,
        ?string $title,
        float $amount,
        $submittedAt,
        string $approvePermission,
        string $href,
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'project_id' => $projectId,
            'project_code' => $projectCode,
            'project_name' => $projectName,
            'document_number' => $documentNumber,
            'title' => $title,
            'amount' => $amount,
            'submitted_at' => $submittedAt?->toISOString(),
            'approve_permission' => $approvePermission,
            'href' => $href,
        ];
    }
}
