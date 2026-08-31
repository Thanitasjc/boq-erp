<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::with('roles')
            ->where('company_id', $request->user()->company_id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => AdminUserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'position' => $validated['position'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->roles()->sync($validated['role_ids']);
        $this->auditLog->log('admin', 'create_user', $user, null, $user->toArray());

        return response()->json([
            'message' => 'User created successfully.',
            'data' => new AdminUserResource($user->load('roles')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeCompanyUser($request, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $roleIds = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);

        $old = $user->toArray();
        $user->update($validated);

        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        $this->auditLog->log('admin', 'update_user', $user, $old, $user->fresh()->toArray());

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new AdminUserResource($user->fresh()->load('roles')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeCompanyUser($request, $user);

        abort_if($user->id === $request->user()->id, 422, 'Cannot delete your own account.');

        $user->tokens()->delete();
        $this->auditLog->log('admin', 'delete_user', $user, $user->toArray());
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function roles(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $roles = Role::orderBy('label')->get();

        return response()->json(['data' => RoleResource::collection($roles)]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasPermission('admin.users'), 403);
    }

    private function authorizeCompanyUser(Request $request, User $user): void
    {
        abort_if($user->company_id !== $request->user()->company_id, 403);
    }
}
