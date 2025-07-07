<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            // Dashboard Module
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'description' => 'Panel principal del sistema',
                'icon' => 'DashboardIcon',
                'type' => 'module',
                'sort_order' => 1,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Dashboard Principal',
                        'slug' => 'dashboard.default',
                        'description' => 'Vista principal del dashboard',
                        'icon' => 'DashboardIcon',
                        'route' => '/dashboard',
                        'component' => 'BusinessDashboard',
                        'permission' => 'dashboard.view',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                ]
            ],

            // Users Module
            [
                'name' => 'Users',
                'slug' => 'users',
                'description' => 'Gestión de usuarios del sistema',
                'icon' => 'PeopleIcon',
                'type' => 'module',
                'sort_order' => 3,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Lista de Usuarios',
                        'slug' => 'users.list',
                        'description' => 'Gestión de usuarios',
                        'icon' => 'PeopleIcon',
                        'route' => '/dashboard/users',
                        'component' => 'Users',
                        'permission' => 'users.view',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Roles',
                        'slug' => 'users.roles',
                        'description' => 'Gestión de roles',
                        'icon' => 'SecurityIcon',
                        'route' => '/dashboard/users/roles',
                        'component' => 'UserRoles',
                        'permission' => 'users.roles',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Permisos',
                        'slug' => 'users.permissions',
                        'description' => 'Gestión de permisos',
                        'icon' => 'VpnKeyIcon',
                        'route' => '/dashboard/users/permissions',
                        'component' => 'UserPermissions',
                        'permission' => 'users.permissions',
                        'type' => 'page',
                        'sort_order' => 3,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                ]
            ],
            
            // System Module
            [
                'name' => 'System',
                'slug' => 'system',
                'description' => 'Configuración del sistema',
                'icon' => 'SettingsIcon',
                'type' => 'module',
                'sort_order' => 6,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Módulos',
                        'slug' => 'system.modules',
                        'description' => 'Gestión de módulos',
                        'icon' => 'AppsIcon',
                        'route' => '/dashboard/modules',
                        'component' => 'Modules',
                        'permission' => 'system.modules',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Configuración',
                        'slug' => 'system.settings',
                        'description' => 'Configuración general',
                        'icon' => 'SettingsIcon',
                        'route' => '/dashboard/settings',
                        'component' => 'Settings',
                        'permission' => 'system.settings',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                ]
            ],
        ];

        $this->createModules($modules);
    }

    private function createModules($modules, $parentId = null)
    {
        foreach ($modules as $moduleData) {
            $children = $moduleData['children'] ?? [];
            unset($moduleData['children']);

            $moduleData['parent_id'] = $parentId;

            $module = Module::create($moduleData);

            if (!empty($children)) {
                $this->createModules($children, $module->id);
            }
        }
    }
}
