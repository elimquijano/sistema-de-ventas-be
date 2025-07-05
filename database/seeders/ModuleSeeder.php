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
                        'component' => 'Dashboard',
                        'permission' => 'dashboard.view',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Analytics',
                        'slug' => 'dashboard.analytics',
                        'description' => 'Dashboard de analytics',
                        'icon' => 'BarChartIcon',
                        'route' => '/dashboard/analytics-dashboard',
                        'component' => 'Analytics',
                        'permission' => 'dashboard.analytics',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                ]
            ],

            // Widget Module
            [
                'name' => 'Widget',
                'slug' => 'widget',
                'description' => 'Módulo de widgets y estadísticas',
                'icon' => 'WidgetsIcon',
                'type' => 'module',
                'sort_order' => 2,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Estadísticas',
                        'slug' => 'widget.statistics',
                        'description' => 'Widget de estadísticas',
                        'icon' => 'TrendingUpIcon',
                        'route' => '/dashboard/statistics',
                        'component' => 'Statistics',
                        'permission' => 'widget.statistics',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Datos',
                        'slug' => 'widget.data',
                        'description' => 'Widget de datos',
                        'icon' => 'StorageIcon',
                        'route' => '/dashboard/data',
                        'component' => 'DataPage',
                        'permission' => 'widget.data',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Gráficos',
                        'slug' => 'widget.chart',
                        'description' => 'Widget de gráficos',
                        'icon' => 'PieChartIcon',
                        'route' => '/dashboard/chart',
                        'component' => 'ChartPage',
                        'permission' => 'widget.chart',
                        'type' => 'page',
                        'sort_order' => 3,
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

            // Customer Module
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Gestión de clientes',
                'icon' => 'GroupIcon',
                'type' => 'module',
                'sort_order' => 4,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Lista de Clientes',
                        'slug' => 'customers.list',
                        'description' => 'Gestión de clientes',
                        'icon' => 'GroupIcon',
                        'route' => '/dashboard/customers',
                        'component' => 'Customers',
                        'permission' => 'customers.view',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Detalles de Cliente',
                        'slug' => 'customers.details',
                        'description' => 'Detalles de cliente',
                        'icon' => 'PersonIcon',
                        'route' => '/dashboard/customers/details',
                        'component' => 'CustomerDetails',
                        'permission' => 'customers.details',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => false, // No se muestra en el menú
                        'auto_create_permissions' => false,
                    ],
                ]
            ],

            // Application Module
            [
                'name' => 'Application',
                'slug' => 'application',
                'description' => 'Aplicaciones del sistema',
                'icon' => 'AppsIcon',
                'type' => 'module',
                'sort_order' => 5,
                'show_in_menu' => true,
                'auto_create_permissions' => false,
                'children' => [
                    [
                        'name' => 'Chat',
                        'slug' => 'chat',
                        'description' => 'Sistema de chat',
                        'icon' => 'ChatIcon',
                        'route' => '/dashboard/chat',
                        'component' => 'Chat',
                        'permission' => 'chat.view',
                        'type' => 'page',
                        'sort_order' => 1,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Kanban',
                        'slug' => 'kanban',
                        'description' => 'Tablero Kanban',
                        'icon' => 'ViewKanbanIcon',
                        'route' => '/dashboard/kanban',
                        'component' => 'Kanban',
                        'permission' => 'kanban.view',
                        'type' => 'page',
                        'sort_order' => 2,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Correo',
                        'slug' => 'mail',
                        'description' => 'Sistema de correo',
                        'icon' => 'MailIcon',
                        'route' => '/dashboard/mail',
                        'component' => 'Mail',
                        'permission' => 'mail.view',
                        'type' => 'page',
                        'sort_order' => 3,
                        'show_in_menu' => true,
                        'auto_create_permissions' => false,
                    ],
                    [
                        'name' => 'Calendario',
                        'slug' => 'calendar',
                        'description' => 'Sistema de calendario',
                        'icon' => 'CalendarTodayIcon',
                        'route' => '/dashboard/calendar',
                        'component' => 'Calendar',
                        'permission' => 'calendar.view',
                        'type' => 'page',
                        'sort_order' => 4,
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
