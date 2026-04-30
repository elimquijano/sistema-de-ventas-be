<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('roles')->truncate();
        DB::table('role_has_permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = [
            ['id' => 1, 'name' => 'Super Admin', 'description' => 'Root del sistema', 'status' => 'active', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Administrador', 'description' => 'Dueño o responsable de un negocio', 'status' => 'active', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Vendedor', 'description' => 'Personal de venta POS', 'status' => 'active', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Delivery', 'description' => 'Motorizado de delivery', 'status' => 'active', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('roles')->insert($roles);

        // --- ASIGNACIÓN DE PERMISOS ---

        $rolePermissions = [
            // Super Admin (ID 1): Gestión de sistema y usuarios
            1 => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],

            // Administrador (ID 2): Operación total + NUEVOS ACTIVOS Y PLANILLA
            2 => [
                1, 2, 3, 4, 5, 8, 10, 12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 
                26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 
                50, 51, 52, 53, 54, 55, 56, 57, 59, 60, 61, 62, 63, 64, 65, 66,
                // NUEVOS: Activos y Planilla
                70, 71, 72, 73, 74, 75, 76, 77, 80, 81, 82, 83
            ],

            // Vendedor (ID 3): Solo POS y ahora ver/devolver balones
            3 => [
                8, 26, 27, 28,
                70, 74, 75, 76 // Ver activos, ver préstamos, crear préstamo y marcar devolución
            ],

            // Delivery (ID 4): Solo Pedidos, Créditos y Devolver balones (SIN préstamos cash)
            4 => [
                8, 42, 58, 59, 63, 64, 65, // Créditos y Pedidos
                74, 75, 76 // Ver sus préstamos de balones, crear préstamo y marcar devolución
            ],
        ];

        $insertBatch = [];
        foreach ($rolePermissions as $roleId => $permissions) {
            foreach ($permissions as $permissionId) {
                $insertBatch[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId
                ];
            }
        }

        DB::table('role_has_permissions')->insert($insertBatch);
    }
}
