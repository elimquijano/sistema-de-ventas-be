<?php
// app/Http/Controllers/ModuleController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $query = Module::with(['parent', 'children']);
        $paginate = $request->input('paginate', 1000);

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $modules = $query->orderBy('sort_order')->paginate($paginate);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'route' => 'nullable|string',
            'component' => 'nullable|string',
            'permission' => 'nullable|string',
            'sort_order' => 'integer',
            'parent_id' => 'nullable|exists:modules,id',
            'type' => 'required|in:module,group,page,button',
            'status' => 'required|in:active,inactive',
            'show_in_menu' => 'boolean',
            'auto_create_permissions' => 'boolean',
        ]);

        $module = Module::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $module->load(['parent', 'children']),
            'message' => 'Módulo creado exitosamente',
        ], 201);
    }

    public function show(Module $module)
    {
        return response()->json([
            'success' => true,
            'data' => $module->load(['parent', 'children', 'permissions']),
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules,slug,' . $module->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'route' => 'nullable|string',
            'component' => 'nullable|string',
            'permission' => 'nullable|string',
            'sort_order' => 'integer',
            'parent_id' => 'nullable|exists:modules,id',
            'type' => 'required|in:module,group,page,button',
            'status' => 'required|in:active,inactive',
            'show_in_menu' => 'boolean',
            'auto_create_permissions' => 'boolean',
        ]);

        $module->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $module->load(['parent', 'children']),
            'message' => 'Módulo actualizado exitosamente',
        ]);
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Módulo eliminado exitosamente',
        ]);
    }

    public function tree()
    {
        $modules = Module::getTree();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    public function menu()
    {
        $user = Auth::user();
        $modules = Module::getMenuTree();

        $filteredModules = $this->filterModulesByPermissions($modules, $user);

        return response()->json([
            'success' => true,
            'data' => $filteredModules,
        ]);
    }

    private function filterModulesByPermissions($modules, $user)
    {
        return $modules->filter(function ($module) use ($user) {
            // Si el módulo tiene un permiso específico, verificarlo
            if ($module->permission && !$user->can($module->permission)) {
                return false;
            }

            // Si es un grupo o módulo, verificar si tiene hijos con permisos
            if (in_array($module->type, ['module', 'group'])) {
                $filteredChildren = $this->filterModulesByPermissions($module->children, $user);
                $module->setRelation('children', $filteredChildren);

                // Solo mostrar si tiene hijos visibles o si es una página con permisos
                return $filteredChildren->count() > 0 ||
                    ($module->type === 'page' && (!$module->permission || $user->can($module->permission)));
            }

            // Para páginas, verificar permiso específico o permiso por defecto
            if ($module->type === 'page') {
                $permission = $module->permission ?: "{$module->slug}.view";
                return $user->can($permission);
            }

            return true;
        })->values();
    }

    public function getRouteConfig()
    {
        $modules = Module::where('status', 'active')
            ->whereNotNull('route')
            ->whereNotNull('component')
            ->orderBy('sort_order')
            ->get();

        $routeConfig = [];

        foreach ($modules as $module) {
            $routeConfig[] = [
                'path' => $module->route,
                'component' => $module->component,
                'permission' => $module->permission ?: "{$module->slug}.view",
                'name' => $module->name,
                'slug' => $module->slug,
                'type' => $module->type,
                'show_in_menu' => $module->show_in_menu,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $routeConfig,
        ]);
    }
}
