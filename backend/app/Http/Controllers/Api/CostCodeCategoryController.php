<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CostCodeCategoryResource;
use App\Models\CostCode;
use App\Models\CostCodeCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CostCodeCategoryController extends Controller
{
    use HandlesListQueries;

    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->user()->company_id;

        $query = CostCodeCategory::where('company_id', $companyId)
            ->withCount(['costCodes' => fn ($q) => $q->where('company_id', $companyId)]);

        $this->applyListFilters($query, $request, ['code', 'name', 'name_en']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->reorder()->orderBy('sort_order')->orderBy('name');

        return CostCodeCategoryResource::collection(
            $query->paginate($request->input('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('cost_code_categories')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['company_id'] = $companyId;
        $validated['code'] = strtolower($validated['code']);
        $category = CostCodeCategory::create($validated);
        $this->auditLog->log('cost_code_categories', 'create', $category);

        return response()->json([
            'message' => 'Cost code category created successfully.',
            'data' => new CostCodeCategoryResource($category),
        ], 201);
    }

    public function show(CostCodeCategory $costCodeCategory): CostCodeCategoryResource
    {
        $this->authorizeCompany($costCodeCategory);

        return new CostCodeCategoryResource($costCodeCategory);
    }

    public function update(Request $request, CostCodeCategory $costCodeCategory): JsonResponse
    {
        $this->authorizeCompany($costCodeCategory);
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'code' => [
                'sometimes', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('cost_code_categories')
                    ->where('company_id', $companyId)
                    ->ignore($costCodeCategory->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $newCode = strtolower($validated['code']);
            if ($newCode !== $costCodeCategory->code) {
                CostCode::where('company_id', $companyId)
                    ->where('category', $costCodeCategory->code)
                    ->update(['category' => $newCode]);
            }
            $validated['code'] = $newCode;
        }

        $costCodeCategory->update($validated);
        $this->auditLog->log('cost_code_categories', 'update', $costCodeCategory);

        return response()->json([
            'message' => 'Cost code category updated successfully.',
            'data' => new CostCodeCategoryResource($costCodeCategory->fresh()),
        ]);
    }

    public function destroy(CostCodeCategory $costCodeCategory): JsonResponse
    {
        $this->authorizeCompany($costCodeCategory);

        $inUse = CostCode::where('company_id', $costCodeCategory->company_id)
            ->where('category', $costCodeCategory->code)
            ->exists();

        abort_if($inUse, 422, 'Cannot delete category that is used by cost codes.');

        $this->auditLog->log('cost_code_categories', 'delete', $costCodeCategory);
        $costCodeCategory->delete();

        return response()->json(['message' => 'Cost code category deleted successfully.']);
    }

    private function authorizeCompany(CostCodeCategory $category): void
    {
        abort_if($category->company_id !== auth()->user()->company_id, 403);
    }
}
