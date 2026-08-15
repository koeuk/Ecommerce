<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /** Resources that follow the standard view/create/update/delete pattern. */
    private const RESOURCES = [
        'product', 'category', 'brand', 'attribute', 'tag',
        'order', 'customer', 'coupon', 'review', 'inventory',
        'report', 'setting', 'user', 'role',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [];

        foreach (self::RESOURCES as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$action} {$resource}";
            }
        }

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Super admin is granted everything via Gate::before, not by permission rows.
        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'web');

        Role::findOrCreate(RoleEnum::Manager->value, 'web')
            ->syncPermissions(
                collect($permissions)
                    ->reject(fn ($p) => str_contains($p, ' role') || $p === 'delete user')
                    ->all()
            );

        Role::findOrCreate(RoleEnum::Staff->value, 'web')
            ->syncPermissions([
                'view product', 'update product',
                'view order', 'update order',
                'view customer',
                'view inventory', 'update inventory',
                'view review', 'update review',
            ]);

        Role::findOrCreate(RoleEnum::Customer->value, 'web');
    }
}
