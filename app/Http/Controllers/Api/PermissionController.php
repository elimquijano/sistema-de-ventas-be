<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Permission::with(['roles']);

        // Filtros
        if ($request->has('search')) {
            $query->search($request->get('search'));
        }

        if ($request->has('module')) {
            $query->byModule($request->get('module'));
        }

        if ($request->has('type')) {
            $query->byType($request->get('type'));
        }

        $permissions = $query->orderBy('module')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions',
            'display_name' => 'required|string|max:255',
            'module' => 'required|string|max:255',
            'module_id' => 'nullable|exists:modules,id',
            'type' => 'required|in:view,create,edit,delete,manage',
            'description' => 'nullable|string',
        ]);

        // Si no se proporciona module_id, intentar encontrarlo por nombre
        if (!$request->module_id && $request->module) {
            $module = Module::where('name', $request->module)->first();
            $request->merge(['module_id' => $module?->id]);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'module' => $request->module,
            'module_id' => $request->module_id,
            'type' => $request->type,
            'description' => $request->description,
            'guard_name' => 'api',
        ]);

        return response()->json([
            'success' => true,
            'data' => $permission,
            'message' => 'Permission created successfully'
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $permission->load(['roles'])
        ]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:255',
            'module' => 'required|string|max:255',
            'module_id' => 'nullable|exists:modules,id',
            'type' => 'required|in:view,create,edit,delete,manage',
            'description' => 'nullable|string',
        ]);

        // Si no se proporciona module_id, intentar encontrarlo por nombre
        if (!$request->module_id && $request->module) {
            $module = Module::where('name', $request->module)->first();
            $request->merge(['module_id' => $module?->id]);
        }

        $permission->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $permission,
            'message' => 'Permission updated successfully'
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }

    public function byModule(Request $request, $moduleId): JsonResponse
    {
        $permissions = Permission::where('module_id', $moduleId)->get();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }
}
