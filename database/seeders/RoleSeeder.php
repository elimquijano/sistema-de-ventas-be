<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Role::truncate();
        DB::table('role_has_permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = [
            ['id' => 1, 'name' => 'Super Admin', 'description' => 'Root del sistema', 'status' => 'active', 'guard_name' => 'api', 'created_at' => '2025-12-17 22:51:38', 'updated_at' => '2025-12-18 00:48:01'],
            ['id' => 2, 'name' => 'Administrador', 'description' => 'Dueño o responsable de un negocio', 'status' => 'active', 'guard_name' => 'api', 'created_at' => '2025-12-18 00:47:39', 'updated_at' => '2025-12-18 00:47:39'],
            ['id' => 3, 'name' => 'Vendedor', 'description' => 'Personal de venta POS', 'status' => 'active', 'guard_name' => 'api', 'created_at' => '2025-12-18 04:14:05', 'updated_at' => '2025-12-18 04:14:05'],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }

        $rolePermissions = [
            // Super Admin
            1 => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
            // Administrador
            2 => [1, 10, 12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49],
            // Vendedor
            3 => [26, 27, 28],
        ];

        foreach ($rolePermissions as $roleId => $permissionIds) {
            $role = Role::find($roleId);
            $permissions = Permission::whereIn('id', $permissionIds)->get();
            $role->syncPermissions($permissions);
        }
    }
}