<?php

namespace App\Exports;

use App\Models\CostLedgerEntry;
use App\Models\DailyReport;
use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CostLedgerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Project $project) {}

    public function collection()
    {
        return CostLedgerEntry::where('project_id', $this->project->id)
            ->with('costCode')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return ['Date', 'Type', 'Description', 'Cost Code', 'Amount', 'Balance'];
    }

    public function map($entry): array
    {
        return [
            $entry->entry_date?->format('Y-m-d'),
            $entry->entry_type,
            $entry->description,
            $entry->costCode?->code ?? '',
            (float) $entry->amount,
            (float) $entry->running_balance,
        ];
    }
}
