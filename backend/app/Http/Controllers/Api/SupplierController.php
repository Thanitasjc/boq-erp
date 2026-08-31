<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    use HandlesListQueries;

    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::where('company_id', $request->user()->company_id);
        $this->applyListFilters($query, $request, ['code', 'name', 'contact_person']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        return SupplierResource::collection(
            $query->paginate($request->input('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:supplier,contractor,both'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $supplier = Supplier::create($validated);
        $this->auditLog->log('suppliers', 'create', $supplier);

        return response()->json([
            'message' => 'Supplier created successfully.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        abort_if($supplier->company_id !== auth()->user()->company_id, 403);

        return new SupplierResource($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        abort_if($supplier->company_id !== auth()->user()->company_id, 403);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:supplier,contractor,both'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier->update($validated);
        $this->auditLog->log('suppliers', 'update', $supplier);

        return response()->json([
            'message' => 'Supplier updated successfully.',
            'data' => new SupplierResource($supplier->fresh()),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        abort_if($supplier->company_id !== auth()->user()->company_id, 403);
        $this->auditLog->log('suppliers', 'delete', $supplier);
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }
}
