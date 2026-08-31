<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = ImportJob::where('company_id', $request->user()->company_id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }
}
