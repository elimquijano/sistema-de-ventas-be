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
            ['name' => 'dashboard.analytics', 'display_name' => 'Ver Analytics', 'module' => 'Dashboard', 'type' => 'view'],

            // Widget permissions
            ['name' => 'widget.statistics', 'display_name' => 'Ver Estadísticas', 'module' => 'Widget', 'type' => 'view'],
            ['name' => 'widget.data', 'display_name' => 'Ver Datos', 'module' => 'Widget', 'type' => 'view'],
            ['name' => 'widget.chart', 'display_name' => 'Ver Gráficos', 'module' => 'Widget', 'type' => 'view'],

            // Users permissions
            ['name' => 'users.view', 'display_name' => 'Ver Usuarios', 'module' => 'Users', 'type' => 'view'],
            ['name' => 'users.create', 'display_name' => 'Crear Usuarios', 'module' => 'Users', 'type' => 'create'],
            ['name' => 'users.edit', 'display_name' => 'Editar Usuarios', 'module' => 'Users', 'type' => 'edit'],
            ['name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'module' => 'Users', 'type' => 'delete'],
            ['name' => 'users.roles', 'display_name' => 'Gestionar Roles', 'module' => 'Users', 'type' => 'manage'],
            ['name' => 'users.permissions', 'display_name' => 'Gestionar Permisos', 'module' => 'Users', 'type' => 'manage'],

            // Customers permissions
            ['name' => 'customers.view', 'display_name' => 'Ver Clientes', 'module' => 'Customer', 'type' => 'view'],
            ['name' => 'customers.create', 'display_name' => 'Crear Clientes', 'module' => 'Customer', 'type' => 'create'],
            ['name' => 'customers.edit', 'display_name' => 'Editar Clientes', 'module' => 'Customer', 'type' => 'edit'],
            ['name' => 'customers.delete', 'display_name' => 'Eliminar Clientes', 'module' => 'Customer', 'type' => 'delete'],
            ['name' => 'customers.details', 'display_name' => 'Ver Detalles de Clientes', 'module' => 'Customer', 'type' => 'view'],

            // Application permissions
            ['name' => 'chat.view', 'display_name' => 'Ver Chat', 'module' => 'Application', 'type' => 'view'],
            ['name' => 'kanban.view', 'display_name' => 'Ver Kanban', 'module' => 'Application', 'type' => 'view'],
            ['name' => 'mail.view', 'display_name' => 'Ver Correo', 'module' => 'Application', 'type' => 'view'],
            ['name' => 'calendar.view', 'display_name' => 'Ver Calendario', 'module' => 'Application', 'type' => 'view'],

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
