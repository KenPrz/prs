<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/roles.json');
        $roleMap = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $allPermissions = [];
        foreach ($roleMap as $permissions) {
            $allPermissions = array_merge($allPermissions, $permissions);
        }
        $allPermissions = array_values(array_unique($allPermissions));

        foreach ($allPermissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach ($roleMap as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
