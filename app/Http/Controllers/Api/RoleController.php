<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::withCount(['users', 'permissions']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ?? 'active',
            'guard_name' => 'api',
        ]);

        if ($request->filled('permission_ids')) {
            $role->syncPermissions($request->permission_ids);
        }

        return response()->json($role->load('permissions'), 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role->update($request->validated());

        if ($request->filled('permission_ids')) {
            $role->syncPermissions($request->permission_ids);
        }

        return response()->json($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users'
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function assignPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->syncPermissions($request->permission_ids);

        return response()->json($role->load('permissions'));
    }
}
