<?php

namespace App\Exports;

use App\Models\BoqVersion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoqExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private BoqVersion $version) {}

    public function collection()
    {
        return $this->version->items()->with(['wbs', 'costCodeRelation', 'uom'])->get();
    }

    public function headings(): array
    {
        return [
            'WBS Code', 'Cost Code', 'Item Code', 'Description', 'Specification',
            'UOM', 'Quantity', 'Material Rate', 'Labor Rate', 'Equipment Rate',
            'Unit Rate', 'Amount', 'Remarks',
        ];
    }

    public function map($item): array
    {
        return [
            $item->wbs_code,
            $item->cost_code,
            $item->item_code,
            $item->description,
            $item->specification,
            $item->uom_code,
            (float) $item->quantity,
            (float) $item->material_rate,
            (float) $item->labor_rate,
            (float) $item->equipment_rate,
            (float) $item->unit_rate,
            (float) $item->amount,
            $item->remarks,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
