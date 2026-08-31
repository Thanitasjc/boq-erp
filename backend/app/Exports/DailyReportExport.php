<?php

namespace App\Exports;

use App\Models\DailyReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(private DailyReport $report) {}

    public function collection()
    {
        return $this->report->items;
    }

    public function headings(): array
    {
        return ['Type', 'Cost Code', 'Description', 'UOM', 'Qty', 'Unit Cost', 'Amount', 'Notes'];
    }

    public function map($item): array
    {
        return [
            $item->item_type,
            $item->cost_code,
            $item->description,
            $item->uom_code,
            (float) $item->quantity,
            (float) $item->unit_cost,
            (float) $item->amount,
            $item->notes,
        ];
    }

    public function title(): string
    {
        return $this->report->document_number ?? 'Daily Report';
    }
}
