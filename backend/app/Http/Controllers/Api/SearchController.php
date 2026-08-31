<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoqVersion;
use App\Models\DailyReport;
use App\Models\ProgressClaim;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\VariationOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        abort_if(strlen($q) < 2, 422, 'ค้นหาอย่างน้อย 2 ตัวอักษร');

        $companyId = $request->user()->company_id;
        $limit = min((int) $request->input('limit', 10), 20);

        $projects = Project::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%");
            })
            ->select('id', 'code', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'type' => 'project',
                'id' => $p->id,
                'label' => "{$p->code} — {$p->name}",
                'href' => "/projects/{$p->id}/dashboard",
            ]);

        $prs = PurchaseRequest::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            })
            ->with('project:id,code')
            ->limit($limit)
            ->get()
            ->map(fn ($pr) => [
                'type' => 'pr',
                'id' => $pr->id,
                'label' => "{$pr->document_number} — {$pr->title}",
                'href' => "/projects/{$pr->project_id}/pr",
            ]);

        $pos = PurchaseOrder::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($po) => [
                'type' => 'po',
                'id' => $po->id,
                'label' => "{$po->document_number} — {$po->title}",
                'href' => "/projects/{$po->project_id}/po",
            ]);

        $boqs = BoqVersion::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('version_number', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($b) => [
                'type' => 'boq',
                'id' => $b->id,
                'label' => "{$b->document_number} — {$b->title}",
                'href' => "/projects/{$b->project_id}/boq/{$b->id}",
            ]);

        $claims = ProgressClaim::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'type' => 'claim',
                'id' => $c->id,
                'label' => "{$c->document_number} — {$c->title}",
                'href' => "/projects/{$c->project_id}/billing",
            ]);

        $vos = VariationOrder::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('vo_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($v) => [
                'type' => 'vo',
                'id' => $v->id,
                'label' => "{$v->document_number} — {$v->title}",
                'href' => "/projects/{$v->project_id}/vo",
            ]);

        $dailyReports = DailyReport::where('company_id', $companyId)
            ->where(function ($query) use ($q) {
                $query->where('document_number', 'like', "%{$q}%")
                    ->orWhere('summary', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'type' => 'daily_report',
                'id' => $d->id,
                'label' => "{$d->document_number} — {$d->summary}",
                'href' => "/projects/{$d->project_id}/daily-report",
            ]);

        $results = $projects->concat($prs)->concat($pos)->concat($boqs)->concat($claims)->concat($vos)->concat($dailyReports)->take($limit);

        return response()->json(['data' => $results->values()]);
    }
}
