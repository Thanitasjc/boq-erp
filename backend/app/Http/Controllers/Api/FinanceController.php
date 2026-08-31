<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(
        private FinanceService $finance,
    ) {}

    public function summary(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json(['data' => $this->finance->getFinanceSummary($project->id)]);
    }

    public function cashFlow(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json(['data' => $this->finance->getCashFlow($project->id)]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_if($project->company_id !== auth()->user()->company_id, 403);
    }
}
