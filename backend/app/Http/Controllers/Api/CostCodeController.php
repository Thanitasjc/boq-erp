<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CostCodeResource;
use App\Models\CostCode;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CostCodeController extends Controller
{
    use HandlesListQueries;

    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CostCode::where('company_id', $request->user()->company_id);

        $this->applyListFilters($query, $request, ['code', 'name']);

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return CostCodeResource::collection(
            $query->paginate($request->input('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => $this->categoryRules($companyId, true),
            'parent_id' => ['nullable', 'exists:cost_codes,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['company_id'] = $companyId;
        $costCode = CostCode::create($validated);
        $this->auditLog->log('cost_codes', 'create', $costCode);

        return response()->json([
            'message' => 'Cost code created successfully.',
            'data' => new CostCodeResource($costCode),
        ], 201);
    }

    public function show(CostCode $costCode): CostCodeResource
    {
        $this->authorizeCompany($costCode);

        return new CostCodeResource($costCode);
    }

    public function update(Request $request, CostCode $costCode): JsonResponse
    {
        $this->authorizeCompany($costCode);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => $this->categoryRules($costCode->company_id, false),
            'parent_id' => ['nullable', 'exists:cost_codes,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $costCode->update($validated);
        $this->auditLog->log('cost_codes', 'update', $costCode);

        return response()->json([
            'message' => 'Cost code updated successfully.',
            'data' => new CostCodeResource($costCode->fresh()),
        ]);
    }

    public function destroy(CostCode $costCode): JsonResponse
    {
        $this->authorizeCompany($costCode);
        $this->auditLog->log('cost_codes', 'delete', $costCode);
        $costCode->delete();

        return response()->json(['message' => 'Cost code deleted successfully.']);
    }

    private function authorizeCompany(CostCode $costCode): void
    {
        abort_if($costCode->company_id !== auth()->user()->company_id, 403);
    }

    private function categoryRules(int $companyId, bool $required): array
    {
        $rules = [
            'string',
            'max:30',
            Rule::exists('cost_code_categories', 'code')->where(
                fn ($query) => $query->where('company_id', $companyId)->where('is_active', true),
            ),
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'sometimes');
        }

        return $rules;
    }
}
