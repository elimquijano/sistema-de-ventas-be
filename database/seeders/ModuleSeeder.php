<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('modules')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $modules = [
            // Dashboard
            ['id' => 1, 'name' => 'Dashboard', 'slug' => 'dashboard', 'description' => 'Panel principal del sistema', 'icon' => 'DashboardIcon', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 1, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Dashboard Principal', 'slug' => 'dashboard.default', 'description' => 'Vista principal del dashboard', 'icon' => 'DashboardIcon', 'route' => '/dashboard/business', 'component' => 'BusinessDashboard', 'permission' => 'dashboard.view', 'sort_order' => 1, 'parent_id' => 1, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            
            // Usuarios
            ['id' => 3, 'name' => 'Users', 'slug' => 'users', 'description' => 'Gestión de usuarios del sistema', 'icon' => 'PeopleIcon', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 2, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Lista de Usuarios', 'slug' => 'users.list', 'description' => 'Gestión de usuarios', 'icon' => 'PeopleIcon', 'route' => '/dashboard/users', 'component' => 'Users', 'permission' => 'users.view', 'sort_order' => 1, 'parent_id' => 3, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Roles', 'slug' => 'users.roles', 'description' => 'Gestión de roles', 'icon' => 'SecurityIcon', 'route' => '/dashboard/users/roles', 'component' => 'UserRoles', 'permission' => 'users.roles', 'sort_order' => 2, 'parent_id' => 3, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Permisos', 'slug' => 'users.permissions', 'description' => 'Gestión de permisos', 'icon' => 'VpnKeyIcon', 'route' => '/dashboard/users/permissions', 'component' => 'UserPermissions', 'permission' => 'users.permissions', 'sort_order' => 3, 'parent_id' => 3, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],

            // Ventas (Subido de prioridad para Deliveries)
            ['id' => 15, 'name' => 'Ventas', 'slug' => 'ventaslist', 'description' => 'Gestión de Ventas', 'icon' => 'ShoppingCart', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 3, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'POS', 'slug' => 'pos', 'description' => 'Punto de Venta', 'icon' => 'PointOfSale', 'route' => '/dashboard/pos', 'component' => 'PointOfSale', 'permission' => 'pos.view', 'sort_order' => 1, 'parent_id' => 15, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Ventas', 'slug' => 'ventas', 'description' => 'Gestión de Ventas', 'icon' => 'Store', 'route' => '/dashboard/ventas', 'component' => 'Sales', 'permission' => 'ventas.view', 'sort_order' => 2, 'parent_id' => 15, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'name' => 'Cajas Registradoras', 'slug' => 'cajas', 'description' => 'Historial de Cajas', 'icon' => 'PointOfSale', 'route' => '/dashboard/cajas', 'component' => 'CashRegisters', 'permission' => 'cajas.view', 'sort_order' => 3, 'parent_id' => 15, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 27, 'name' => 'Gestor de Pedidos', 'slug' => 'gestor-pedidos', 'description' => 'Gestión de pedidos', 'icon' => 'Menu', 'route' => '/dashboard/gestor-de-pedidos', 'component' => 'Orders', 'permission' => 'gestordepedidos.view', 'sort_order' => 4, 'parent_id' => 15, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 28, 'name' => 'Pedidos', 'slug' => 'pedidos', 'description' => 'Pedidos pendientes', 'icon' => 'LocalShipping', 'route' => '/dashboard/pedidos', 'component' => 'RiderOrders', 'permission' => 'pedidos.view', 'sort_order' => 5, 'parent_id' => 15, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],

            // Administración de Control Interno
            ['id' => 10, 'name' => 'Administración', 'slug' => 'administracion', 'description' => 'Gestión de Control Interno', 'icon' => 'SettingsApplications', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 4, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Negocios', 'slug' => 'negocios', 'description' => 'Gestión de Negocios', 'icon' => 'Business', 'route' => '/dashboard/negocios', 'component' => 'Business', 'permission' => 'negocios.view', 'sort_order' => 1, 'parent_id' => 10, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Categorias', 'slug' => 'categorias', 'description' => 'Gestión de Categorías', 'icon' => 'Menu', 'route' => '/dashboard/categorias', 'component' => 'Categories', 'permission' => 'categorias.view', 'sort_order' => 2, 'parent_id' => 10, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'Productos', 'slug' => 'productos', 'description' => 'Gestión de Productos', 'icon' => 'Store', 'route' => '/dashboard/productos', 'component' => 'Products', 'permission' => 'productos.view', 'sort_order' => 3, 'parent_id' => 10, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'name' => 'Servicios', 'slug' => 'servicios', 'description' => 'Gestión de Servicios', 'icon' => 'Build', 'route' => '/dashboard/servicios', 'component' => 'Services', 'permission' => 'servicios.view', 'sort_order' => 4, 'parent_id' => 10, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 26, 'name' => 'Clientes', 'slug' => 'clientes', 'description' => 'Gestión de clientes', 'icon' => 'PeopleIcon', 'route' => '/dashboard/clientes', 'component' => 'Clients', 'permission' => 'clientes.view', 'sort_order' => 5, 'parent_id' => 10, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            
            // NUEVO: Bienes y Activos (Fierros) - FALTABA ESTE ID 30
            ['id' => 30, 'name' => 'Bienes y Activos', 'slug' => 'activos', 'description' => 'Gestión de bienes del negocio', 'icon' => 'Inventory', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 6, 'parent_id' => 10, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'name' => 'Inventario Activos', 'slug' => 'activos.list', 'description' => 'Lista de activos/bienes', 'icon' => 'ViewList', 'route' => '/dashboard/activos', 'component' => 'Assets', 'permission' => 'assets.view', 'sort_order' => 1, 'parent_id' => 30, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'name' => 'Préstamo de Bienes', 'slug' => 'activos.loans', 'description' => 'Préstamos de fierros/balones', 'icon' => 'Output', 'route' => '/dashboard/activos/prestamos', 'component' => 'AssetLoans', 'permission' => 'assets.loan.view', 'sort_order' => 2, 'parent_id' => 30, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],

            // Operaciones (Compras, Gastos, Planilla)
            ['id' => 40, 'name' => 'Operaciones', 'slug' => 'operaciones', 'description' => 'Gastos, Compras y Planilla', 'icon' => 'AccountBalance', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 5, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'Compras', 'slug' => 'compras', 'description' => 'Gestión de Compras', 'icon' => 'Inventory', 'route' => '/dashboard/compras', 'component' => 'Purchases', 'permission' => 'compras.view', 'sort_order' => 1, 'parent_id' => 40, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'name' => 'Gastos', 'slug' => 'merma', 'description' => 'Gestión de Gastos', 'icon' => 'Receipt', 'route' => '/dashboard/gastos', 'component' => 'Expenses', 'permission' => 'merma.view', 'sort_order' => 2, 'parent_id' => 40, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'name' => 'Créditos', 'slug' => 'creditos', 'description' => 'Gestión de Créditos', 'icon' => 'CreditCard', 'route' => '/dashboard/creditos', 'component' => 'Credits', 'permission' => 'creditos.view', 'sort_order' => 3, 'parent_id' => 40, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'name' => 'Préstamos Cash', 'slug' => 'prestamos', 'description' => 'Préstamos de dinero', 'icon' => 'AttachMoney', 'route' => '/dashboard/prestamos', 'component' => 'Loans', 'permission' => 'prestamos.view', 'sort_order' => 4, 'parent_id' => 40, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],
            
            // NUEVO: Planilla (Payroll)
            ['id' => 41, 'name' => 'Planilla y Personal', 'slug' => 'payroll', 'description' => 'Control de sueldos y asistencia', 'icon' => 'AssignmentInd', 'route' => '/dashboard/planilla', 'component' => 'Payroll', 'permission' => 'payroll.view', 'sort_order' => 5, 'parent_id' => 40, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 1, 'created_at' => now(), 'updated_at' => now()],

            // System
            ['id' => 7, 'name' => 'System', 'slug' => 'system', 'description' => 'Configuración del sistema', 'icon' => 'SettingsIcon', 'route' => null, 'component' => null, 'permission' => null, 'sort_order' => 6, 'parent_id' => null, 'type' => 'module', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Módulos', 'slug' => 'system.modules', 'description' => 'Gestión de módulos', 'icon' => 'AppsIcon', 'route' => '/dashboard/modules', 'component' => 'Modules', 'permission' => 'system.modules', 'sort_order' => 1, 'parent_id' => 7, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Configuración', 'slug' => 'system.settings', 'description' => 'Configuración general', 'icon' => 'SettingsIcon', 'route' => '/dashboard/settings', 'component' => 'SettingsPage', 'permission' => 'system.settings', 'sort_order' => 2, 'parent_id' => 7, 'type' => 'page', 'status' => 'active', 'show_in_menu' => 1, 'auto_create_permissions' => 0, 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('modules')->insert($modules);
    }
}
