<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompanyDashboardExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private Collection $projects) {}

    public function collection(): Collection
    {
        return $this->projects;
    }

    public function headings(): array
    {
        return ['รหัส', 'โครงการ', 'ลูกค้า', 'สถานะ', 'PM', 'มูลค่าสัญญา', 'งบประมาณ', 'วันเริ่ม', 'วันสิ้นสุด'];
    }

    public function map($project): array
    {
        return [
            $project->code,
            $project->name,
            $project->client_name,
            $project->status,
            $project->projectManager?->name,
            (float) $project->contract_value,
            (float) $project->revised_budget,
            $project->start_date?->format('Y-m-d'),
            $project->end_date?->format('Y-m-d'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
