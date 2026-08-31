<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalInboxService $inbox,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $type = $request->input('type');
        $items = $this->inbox->pending($companyId, $type);

        return response()->json([
            'data' => $items,
            'meta' => ['total' => $items->count()],
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        return response()->json([
            'data' => [
                'total' => $this->inbox->count($companyId),
                'by_type' => [
                    'boq' => $this->inbox->pending($companyId, 'boq')->count(),
                    'budget' => $this->inbox->pending($companyId, 'budget')->count(),
                    'pr' => $this->inbox->pending($companyId, 'pr')->count(),
                    'po' => $this->inbox->pending($companyId, 'po')->count(),
                    'claim' => $this->inbox->pending($companyId, 'claim')->count(),
                    'vo' => $this->inbox->pending($companyId, 'vo')->count(),
                    'daily_report' => $this->inbox->pending($companyId, 'daily_report')->count(),
                ],
            ],
        ]);
    }
}
