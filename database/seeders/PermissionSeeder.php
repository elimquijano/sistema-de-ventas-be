<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Dashboard permissions
            ['name' => 'dashboard.view', 'display_name' => 'Ver Dashboard', 'module' => 'Dashboard', 'type' => 'view'],

            // Users permissions
            ['name' => 'users.view', 'display_name' => 'Ver Usuarios', 'module' => 'Users', 'type' => 'view'],
            ['name' => 'users.create', 'display_name' => 'Crear Usuarios', 'module' => 'Users', 'type' => 'create'],
            ['name' => 'users.edit', 'display_name' => 'Editar Usuarios', 'module' => 'Users', 'type' => 'edit'],
            ['name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'module' => 'Users', 'type' => 'delete'],
            ['name' => 'users.roles', 'display_name' => 'Gestionar Roles', 'module' => 'Users', 'type' => 'manage'],
            ['name' => 'users.permissions', 'display_name' => 'Gestionar Permisos', 'module' => 'Users', 'type' => 'manage'],

            // System permissions
            ['name' => 'system.settings', 'display_name' => 'Configuración del Sistema', 'module' => 'System', 'type' => 'manage'],
            ['name' => 'system.modules', 'display_name' => 'Gestionar Módulos', 'module' => 'System', 'type' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            // Buscar el módulo correspondiente
            $module = Module::where('name', $permission['module'])->first();

            Permission::create([
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'module' => $permission['module'],
                'module_id' => $module?->id,
                'type' => $permission['type'],
                'description' => $permission['display_name'],
                'guard_name' => 'api',
            ]);
        }
    }
}
