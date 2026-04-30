<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $permissions = [
            // Dashboard
            ['id' => 1, 'name' => 'dashboard.view', 'display_name' => 'Ver Dashboard', 'module' => 'Dashboard', 'module_id' => 1, 'type' => 'view', 'description' => 'Ver Dashboard', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Usuarios
            ['id' => 2, 'name' => 'users.view', 'display_name' => 'Ver Usuarios', 'module' => 'Users', 'module_id' => 3, 'type' => 'view', 'description' => 'Ver Usuarios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'users.create', 'display_name' => 'Crear Usuarios', 'module' => 'Users', 'module_id' => 3, 'type' => 'create', 'description' => 'Crear Usuarios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'users.edit', 'display_name' => 'Editar Usuarios', 'module' => 'Users', 'module_id' => 3, 'type' => 'edit', 'description' => 'Editar Usuarios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'module' => 'Users', 'module_id' => 3, 'type' => 'delete', 'description' => 'Eliminar Usuarios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'users.roles', 'display_name' => 'Gestionar Roles', 'module' => 'Users', 'module_id' => 3, 'type' => 'manage', 'description' => 'Gestionar Roles', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'users.permissions', 'display_name' => 'Gestionar Permisos', 'module' => 'Users', 'module_id' => 3, 'type' => 'manage', 'description' => 'Gestionar Permisos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Sistema
            ['id' => 8, 'name' => 'system.settings', 'display_name' => 'Configuración del Sistema', 'module' => 'System', 'module_id' => 7, 'type' => 'manage', 'description' => 'Configuración del Sistema', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'system.modules', 'display_name' => 'Gestionar Módulos', 'module' => 'System', 'module_id' => 7, 'type' => 'manage', 'description' => 'Gestionar Módulos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Negocios
            ['id' => 10, 'name' => 'negocios.view', 'display_name' => 'Ver Negocios', 'module' => 'Negocios', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Negocios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'negocios.create', 'display_name' => 'Crear Negocios', 'module' => 'Negocios', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Negocios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'negocios.edit', 'display_name' => 'Editar Negocios', 'module' => 'Negocios', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Negocios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'name' => 'negocios.delete', 'display_name' => 'Eliminar Negocios', 'module' => 'Negocios', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Negocios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Productos
            ['id' => 14, 'name' => 'productos.view', 'display_name' => 'Ver Productos', 'module' => 'Productos', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Productos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'name' => 'productos.create', 'display_name' => 'Crear Productos', 'module' => 'Productos', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Productos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'productos.edit', 'display_name' => 'Editar Productos', 'module' => 'Productos', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Productos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'productos.delete', 'display_name' => 'Eliminar Productos', 'module' => 'Productos', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Productos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Servicios
            ['id' => 18, 'name' => 'servicios.view', 'display_name' => 'Ver Servicios', 'module' => 'Servicios', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Servicios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'name' => 'servicios.create', 'display_name' => 'Crear Servicios', 'module' => 'Servicios', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Servicios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'servicios.edit', 'display_name' => 'Editar Servicios', 'module' => 'Servicios', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Servicios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'servicios.delete', 'display_name' => 'Eliminar Servicios', 'module' => 'Servicios', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Servicios', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Categorias
            ['id' => 22, 'name' => 'categorias.view', 'display_name' => 'Ver Categorias', 'module' => 'Categorias', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Categorias', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'name' => 'categorias.create', 'display_name' => 'Crear Categorias', 'module' => 'Categorias', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Categorias', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'name' => 'categorias.edit', 'display_name' => 'Editar Categorias', 'module' => 'Categorias', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Categorias', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'name' => 'categorias.delete', 'display_name' => 'Eliminar Categorias', 'module' => 'Categorias', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Categorias', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // POS y Ventas
            ['id' => 26, 'name' => 'pos.view', 'display_name' => 'Ver POS', 'module' => 'POS', 'module_id' => null, 'type' => 'view', 'description' => 'Ver POS', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 27, 'name' => 'pos.create', 'display_name' => 'Crear POS', 'module' => 'POS', 'module_id' => null, 'type' => 'create', 'description' => 'Crear POS', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 28, 'name' => 'pos.edit', 'display_name' => 'Editar POS', 'module' => 'POS', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar POS', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 29, 'name' => 'pos.delete', 'display_name' => 'Eliminar POS', 'module' => 'POS', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar POS', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'name' => 'ventas.view', 'display_name' => 'Ver Ventas', 'module' => 'Ventas', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Ventas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'name' => 'ventas.create', 'display_name' => 'Crear Ventas', 'module' => 'Ventas', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Ventas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'name' => 'ventas.edit', 'display_name' => 'Editar Ventas', 'module' => 'Ventas', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Ventas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 33, 'name' => 'ventas.delete', 'display_name' => 'Eliminar Ventas', 'module' => 'Ventas', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Ventas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Compras y Gastos
            ['id' => 34, 'name' => 'compras.view', 'display_name' => 'Ver Compras', 'module' => 'Compras', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Compras', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'name' => 'compras.create', 'display_name' => 'Crear Compras', 'module' => 'Compras', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Compras', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'name' => 'compras.edit', 'display_name' => 'Editar Compras', 'module' => 'Compras', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Compras', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 37, 'name' => 'compras.delete', 'display_name' => 'Eliminar Compras', 'module' => 'Compras', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Compras', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 38, 'name' => 'merma.view', 'display_name' => 'Ver Gastos', 'module' => 'Gastos', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Gastos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 39, 'name' => 'merma.create', 'display_name' => 'Crear Gastos', 'module' => 'Gastos', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Gastos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'name' => 'merma.edit', 'display_name' => 'Editar Gastos', 'module' => 'Gastos', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Gastos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 41, 'name' => 'merma.delete', 'display_name' => 'Eliminar Gastos', 'module' => 'Gastos', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Gastos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Créditos y Préstamos
            ['id' => 42, 'name' => 'creditos.view', 'display_name' => 'Ver Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Créditos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 43, 'name' => 'creditos.create', 'display_name' => 'Crear Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Créditos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 44, 'name' => 'creditos.edit', 'display_name' => 'Editar Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Créditos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 45, 'name' => 'creditos.delete', 'display_name' => 'Eliminar Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Créditos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 46, 'name' => 'prestamos.view', 'display_name' => 'Ver Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Préstamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 47, 'name' => 'prestamos.create', 'display_name' => 'Crear Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Préstamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 48, 'name' => 'prestamos.edit', 'display_name' => 'Editar Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Préstamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 49, 'name' => 'prestamos.delete', 'display_name' => 'Eliminar Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Préstamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Cajas y Clientes
            ['id' => 50, 'name' => 'cajas.view', 'display_name' => 'Ver Cajas', 'module' => 'Cajas', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Cajas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 51, 'name' => 'cajas.create', 'display_name' => 'Crear Cajas', 'module' => 'Cajas', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Cajas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 52, 'name' => 'cajas.edit', 'display_name' => 'Editar Cajas', 'module' => 'Cajas', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Cajas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 53, 'name' => 'cajas.delete', 'display_name' => 'Eliminar Cajas', 'module' => 'Cajas', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Cajas', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 54, 'name' => 'clientes.view', 'display_name' => 'Ver Clientes', 'module' => 'Clientes', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Clientes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 55, 'name' => 'clientes.create', 'display_name' => 'Crear Clientes', 'module' => 'Clientes', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Clientes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 56, 'name' => 'clientes.edit', 'display_name' => 'Editar Clientes', 'module' => 'Clientes', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Clientes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 57, 'name' => 'clientes.delete', 'display_name' => 'Eliminar Clientes', 'module' => 'Clientes', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Clientes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            
            // Pedidos y Gestor (Desde Backup)
            ['id' => 58, 'name' => 'pedidos.view', 'display_name' => 'Ver Pedidos', 'module' => 'Pedidos', 'module_id' => null, 'type' => 'view', 'description' => 'Ver Pedidos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 59, 'name' => 'creditos.pay', 'display_name' => 'Pagar Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'view', 'description' => 'Pagar Creditos pendientes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 60, 'name' => 'creditos.audit', 'display_name' => 'Auditoría a Créditos', 'module' => 'Créditos', 'module_id' => null, 'type' => 'view', 'description' => 'Auditoria a Creditos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 61, 'name' => 'prestamos.pay', 'display_name' => 'Pagar Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'view', 'description' => 'Pagar prestamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 62, 'name' => 'prestamos.audit', 'display_name' => 'Auditoría a Préstamos', 'module' => 'Préstamos', 'module_id' => null, 'type' => 'view', 'description' => 'Auditoria a Prestamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 63, 'name' => 'pedidos.create', 'display_name' => 'Crear Pedidos', 'module' => 'Pedidos', 'module_id' => null, 'type' => 'create', 'description' => 'Crear Pedidos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 64, 'name' => 'pedidos.edit', 'display_name' => 'Editar Pedidos', 'module' => 'Pedidos', 'module_id' => null, 'type' => 'edit', 'description' => 'Editar Pedidos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 65, 'name' => 'pedidos.delete', 'display_name' => 'Eliminar Pedidos', 'module' => 'Pedidos', 'module_id' => null, 'type' => 'delete', 'description' => 'Eliminar Pedidos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 66, 'name' => 'gestordepedidos.view', 'display_name' => 'Gestor de Pedidos', 'module' => 'Gestor de Pedidos', 'module_id' => null, 'type' => 'view', 'description' => 'Gestor de pedidos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],

            // NUEVOS PERMISOS: Activos (Bienes)
            ['id' => 70, 'name' => 'assets.view', 'display_name' => 'Ver Activos', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'view', 'description' => 'Ver bienes del negocio', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 71, 'name' => 'assets.create', 'display_name' => 'Crear Activos', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'create', 'description' => 'Registrar nuevos bienes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 72, 'name' => 'assets.edit', 'display_name' => 'Editar Activos', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'edit', 'description' => 'Editar bienes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 73, 'name' => 'assets.delete', 'display_name' => 'Eliminar Activos', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'delete', 'description' => 'Eliminar bienes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 74, 'name' => 'assets.loan.view', 'display_name' => 'Ver Préstamos de Bienes', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'view', 'description' => 'Ver préstamos de fierros/balones', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 75, 'name' => 'assets.loan.create', 'display_name' => 'Crear Préstamo de Bienes', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'create', 'description' => 'Registrar préstamo de fierros', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 76, 'name' => 'assets.loan.complete', 'display_name' => 'Completar Devolución', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'edit', 'description' => 'Marcar devolución de bienes', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 77, 'name' => 'assets.loan.audit', 'display_name' => 'Ver Auditoría de Préstamos', 'module' => 'Bienes', 'module_id' => 30, 'type' => 'view', 'description' => 'Ver historial de cambios en préstamos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],

            // NUEVOS PERMISOS: Planilla (Payroll)
            ['id' => 80, 'name' => 'payroll.view', 'display_name' => 'Ver Planilla', 'module' => 'Planilla', 'module_id' => 41, 'type' => 'view', 'description' => 'Ver resumen de sueldos', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 81, 'name' => 'payroll.manage', 'display_name' => 'Gestionar Configuración Nómina', 'module' => 'Planilla', 'module_id' => 41, 'type' => 'manage', 'description' => 'Configurar sueldos base', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 82, 'name' => 'payroll.attendance', 'display_name' => 'Registrar Asistencia', 'module' => 'Planilla', 'module_id' => 41, 'type' => 'create', 'description' => 'Marcar asistencia diaria', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 83, 'name' => 'payroll.advances', 'display_name' => 'Gestionar Adelantos', 'module' => 'Planilla', 'module_id' => 41, 'type' => 'manage', 'description' => 'Crear y ver adelantos de sueldo', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('permissions')->insert($permissions);
    }
}
