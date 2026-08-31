<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UomResource;
use App\Models\Uom;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UomController extends Controller
{
    use HandlesListQueries;

    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Uom::where('company_id', $request->user()->company_id);
        $this->applyListFilters($query, $request, ['code', 'name']);

        return UomResource::collection(
            $query->paginate($request->input('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $uom = Uom::create($validated);
        $this->auditLog->log('uoms', 'create', $uom);

        return response()->json([
            'message' => 'UOM created successfully.',
            'data' => new UomResource($uom),
        ], 201);
    }

    public function show(Uom $uom): UomResource
    {
        abort_if($uom->company_id !== auth()->user()->company_id, 403);

        return new UomResource($uom);
    }

    public function update(Request $request, Uom $uom): JsonResponse
    {
        abort_if($uom->company_id !== auth()->user()->company_id, 403);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:20'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $uom->update($validated);
        $this->auditLog->log('uoms', 'update', $uom);

        return response()->json([
            'message' => 'UOM updated successfully.',
            'data' => new UomResource($uom->fresh()),
        ]);
    }

    public function destroy(Uom $uom): JsonResponse
    {
        abort_if($uom->company_id !== auth()->user()->company_id, 403);
        $this->auditLog->log('uoms', 'delete', $uom);
        $uom->delete();

        return response()->json(['message' => 'UOM deleted successfully.']);
    }
}
