<?php

namespace App\Services;

use App\Models\DocumentNumberSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function generate(int $companyId, string $documentType, string $prefix): string
    {
        $year = (int) date('Y');

        return DB::transaction(function () use ($companyId, $documentType, $prefix, $year) {
            $sequence = DocumentNumberSequence::lockForUpdate()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'year' => $year,
                ],
                ['prefix' => $prefix, 'last_number' => 0]
            );

            $sequence->increment('last_number');
            $number = str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);

            return "{$prefix}-{$year}-{$number}";
        });
    }
}
