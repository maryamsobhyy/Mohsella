<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'user create'],
            ['name' => 'user update'],
            ['name' => 'user delete'],
            ['name' => 'user show'],
            ['name' => 'user index'],



        ]);

        $permissions->each(function ($permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
            );
        });

        $roles = [
            'super-admin',
            'admin',
            'user',
        ];
        foreach ($roles as $role) {
            Role::updateOrCreate([
                'name' => $role,
                'guard_name' => 'api',
            ]);
        }

        $superAdminApi = Role::where(['name' => 'super-admin', 'guard_name' => 'api'])->firstOrFail();
        $superAdminApi->givePermissionTo(Permission::where('guard_name', 'api')->get());

        $adminWeb = Role::where(['name' => 'admin', 'guard_name' => 'api'])->firstOrFail();
        $adminWeb->givePermissionTo([]);

        $userWeb = Role::where(['name' => 'user', 'guard_name' => 'api'])->firstOrFail();
        $userWeb->givePermissionTo([]);


    }
}
