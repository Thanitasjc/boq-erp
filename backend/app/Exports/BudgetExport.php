<?php

namespace App\Exports;

use App\Models\Budget;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private Budget $budget) {}

    public function collection()
    {
        return $this->budget->lines;
    }

    public function headings(): array
    {
        return ['Cost Code', 'Name', 'BOQ Amount', 'Budget Amount', 'Committed', 'Actual', 'Remaining'];
    }

    public function map($line): array
    {
        return [
            $line->cost_code,
            $line->cost_code_name,
            (float) $line->boq_amount,
            (float) $line->budget_amount,
            (float) $line->committed_amount,
            (float) $line->actual_amount,
            (float) $line->budget_amount - (float) $line->actual_amount,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
