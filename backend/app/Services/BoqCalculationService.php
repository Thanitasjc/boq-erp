<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\BoqVersion;
use App\Models\CostCode;
use App\Models\Uom;
use App\Models\WbsNode;

class BoqCalculationService
{
    public function calculateItemRates(array $data): array
    {
        $material = (float) ($data['material_rate'] ?? 0);
        $labor = (float) ($data['labor_rate'] ?? 0);
        $equipment = (float) ($data['equipment_rate'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 0);

        $unitRate = isset($data['unit_rate']) && $data['unit_rate'] > 0
            ? (float) $data['unit_rate']
            : $material + $labor + $equipment;

        $data['unit_rate'] = $unitRate;
        $data['amount'] = round($quantity * $unitRate, 2);

        return $data;
    }

    public function recalculateVersionTotal(BoqVersion $version): void
    {
        $total = $version->items()->sum('amount');
        $version->update(['total_amount' => $total]);
    }

    public function resolveMasterReferences(int $companyId, ?int $projectId, array $data): array
    {
        if (! empty($data['wbs_code']) && empty($data['wbs_id'])) {
            $wbs = WbsNode::where('company_id', $companyId)
                ->where('code', $data['wbs_code'])
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->first();
            $data['wbs_id'] = $wbs?->id;
        }

        if (! empty($data['cost_code']) && empty($data['cost_code_id'])) {
            $cc = CostCode::where('company_id', $companyId)
                ->where('code', $data['cost_code'])
                ->first();
            $data['cost_code_id'] = $cc?->id;
        }

        if (! empty($data['uom_code']) && empty($data['uom_id'])) {
            $uom = Uom::where('company_id', $companyId)
                ->where('code', $data['uom_code'])
                ->first();
            $data['uom_id'] = $uom?->id;
        }

        return $data;
    }

    public function prepareItemData(int $companyId, int $projectId, int $versionId, array $data, int $sortOrder = 0): array
    {
        $data = $this->resolveMasterReferences($companyId, $projectId, $data);
        $data = $this->calculateItemRates($data);

        return array_merge($data, [
            'company_id' => $companyId,
            'project_id' => $projectId,
            'boq_version_id' => $versionId,
            'sort_order' => $sortOrder,
        ]);
    }
}
