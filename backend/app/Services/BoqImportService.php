<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\BoqVersion;
use App\Models\CostCode;
use App\Models\ImportJob;
use App\Models\Uom;
use App\Models\WbsNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BoqImportService
{
    public const FIELD_MAP = [
        'wbs_code' => ['wbs code', 'wbs', 'wbs_code', 'รหัส wbs'],
        'cost_code' => ['cost code', 'cost_code', 'รหัส cost'],
        'item_code' => ['item code', 'item_code', 'รหัส'],
        'description' => ['description', 'desc', 'รายการ', 'รายละเอียด'],
        'specification' => ['specification', 'spec', 'สเปค'],
        'uom_code' => ['uom', 'uom code', 'uom_code', 'หน่วย'],
        'quantity' => ['quantity', 'qty', 'ปริมาณ'],
        'material_rate' => ['material rate', 'material_rate', 'ค่าวัสดุ'],
        'labor_rate' => ['labor rate', 'labor_rate', 'ค่าแรง'],
        'equipment_rate' => ['equipment rate', 'equipment_rate', 'ค่าเครื่องจักร'],
        'unit_rate' => ['unit rate', 'unit_rate', 'ราคาต่อหน่วย'],
        'amount' => ['amount', 'total', 'จำนวนเงิน'],
        'remarks' => ['remarks', 'remark', 'หมายเหตุ'],
    ];

    public function __construct(
        private BoqCalculationService $calculator,
        private AuditLogService $auditLog,
    ) {}

    public function preview(UploadedFile $file, ?array $columnMap = null): array
    {
        $rows = $this->readFile($file);
        if (empty($rows)) {
            return ['headers' => [], 'mapped_columns' => [], 'rows' => [], 'errors' => ['File is empty']];
        }

        $headers = array_map('trim', array_map('strval', $rows[0]));
        $mappedColumns = $columnMap ?? $this->autoMapColumns($headers);
        $validatedRows = [];
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNum = $index + 2;
            $parsed = $this->parseRow($headers, $row, $mappedColumns);
            if ($this->isEmptyRow($parsed)) {
                continue;
            }
            $rowErrors = $this->validateRow($parsed, $rowNum);
            $validatedRows[] = [
                'row' => $rowNum,
                'data' => $parsed,
                'errors' => $rowErrors,
                'warnings' => $this->getRowWarnings($parsed),
                'status' => empty($rowErrors) ? (empty($this->getRowWarnings($parsed)) ? 'valid' : 'warning') : 'error',
            ];
            if (! empty($rowErrors)) {
                $errors[] = "Row {$rowNum}: ".implode(', ', $rowErrors);
            }
        }

        return [
            'headers' => $headers,
            'mapped_columns' => $mappedColumns,
            'rows' => $validatedRows,
            'summary' => [
                'total' => count($validatedRows),
                'valid' => count(array_filter($validatedRows, fn ($r) => $r['status'] === 'valid')),
                'warning' => count(array_filter($validatedRows, fn ($r) => $r['status'] === 'warning')),
                'error' => count(array_filter($validatedRows, fn ($r) => $r['status'] === 'error')),
            ],
            'errors' => $errors,
        ];
    }

    public function import(
        BoqVersion $version,
        UploadedFile $file,
        array $columnMap,
        int $userId,
        bool $replaceExisting = false,
    ): ImportJob {
        $storedPath = $file->store('imports/boq', 'local');
        $preview = $this->preview($file, $columnMap);

        $job = ImportJob::create([
            'company_id' => $version->company_id,
            'project_id' => $version->project_id,
            'user_id' => $userId,
            'module' => 'boq',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => 'processing',
            'total_rows' => $preview['summary']['total'],
            'metadata' => ['column_map' => $columnMap, 'boq_version_id' => $version->id],
        ]);

        $success = 0;
        $failed = 0;
        $warnings = 0;
        $errorRows = [];

        DB::transaction(function () use ($version, $preview, $replaceExisting, &$success, &$failed, &$warnings, &$errorRows) {
            if ($replaceExisting) {
                $version->items()->delete();
            }

            $sortOrder = (int) $version->items()->max('sort_order');

            foreach ($preview['rows'] as $row) {
                if ($row['status'] === 'error') {
                    $failed++;
                    $errorRows[] = $row;
                    continue;
                }

                if ($row['status'] === 'warning') {
                    $warnings++;
                }

                $sortOrder++;
                $itemData = $this->calculator->prepareItemData(
                    $version->company_id,
                    $version->project_id,
                    $version->id,
                    $row['data'],
                    $sortOrder,
                );

                BoqItem::create($itemData);
                $success++;
            }

            $this->calculator->recalculateVersionTotal($version);
        });

        $errorReportPath = null;
        if (! empty($errorRows)) {
            $errorReportPath = $this->generateErrorReport($job->id, $errorRows);
        }

        $job->update([
            'status' => 'completed',
            'success_rows' => $success,
            'failed_rows' => $failed,
            'warning_rows' => $warnings,
            'error_report_path' => $errorReportPath,
            'summary' => [
                'total' => $preview['summary']['total'],
                'success' => $success,
                'failed' => $failed,
                'warning' => $warnings,
            ],
        ]);

        $this->auditLog->log('boq', 'import', $version, null, $job->summary, $version->project_id);

        return $job->fresh();
    }

    private function readFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        return $spreadsheet->getActiveSheet()->toArray();
    }

    public function autoMapColumns(array $headers): array
    {
        $map = [];
        foreach (self::FIELD_MAP as $field => $aliases) {
            foreach ($headers as $index => $header) {
                $normalized = strtolower(trim($header));
                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        return $map;
    }

    private function parseRow(array $headers, array $row, array $columnMap): array
    {
        $parsed = [];
        foreach ($columnMap as $field => $index) {
            $parsed[$field] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }
        return $parsed;
    }

    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $v !== null && $v !== ''));
    }

    private function validateRow(array $data, int $rowNum): array
    {
        $errors = [];
        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }
        if (isset($data['quantity']) && $data['quantity'] !== '' && ! is_numeric($data['quantity'])) {
            $errors[] = 'Quantity must be numeric';
        }
        if (isset($data['quantity']) && is_numeric($data['quantity']) && (float) $data['quantity'] < 0) {
            $errors[] = 'Quantity cannot be negative';
        }
        foreach (['material_rate', 'labor_rate', 'equipment_rate', 'unit_rate', 'amount'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && ! is_numeric($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' must be numeric';
            }
        }
        return $errors;
    }

    private function getRowWarnings(array $data): array
    {
        $warnings = [];
        if (! empty($data['cost_code'])) {
            // warnings resolved at company level during preview only as info
        }
        if (empty($data['uom_code'])) {
            $warnings[] = 'UOM is empty';
        }
        return $warnings;
    }

    private function generateErrorReport(int $jobId, array $errorRows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Row', 'Errors', 'Data'], null, 'A1');

        $rowNum = 2;
        foreach ($errorRows as $error) {
            $sheet->setCellValue("A{$rowNum}", $error['row']);
            $sheet->setCellValue("B{$rowNum}", implode('; ', $error['errors']));
            $sheet->setCellValue("C{$rowNum}", json_encode($error['data']));
            $rowNum++;
        }

        $path = "imports/reports/error_{$jobId}.xlsx";
        $fullPath = Storage::disk('local')->path($path);
        @mkdir(dirname($fullPath), 0755, true);
        (new Xlsx($spreadsheet))->save($fullPath);

        return $path;
    }
}
