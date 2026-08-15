<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ReferenceDataSeeder::class,
            CatalogStructureSeeder::class,
            ProductSeeder::class,
        ]);

        $this->accounts();
    }

    private function accounts(): void
    {
        User::factory()
            ->create([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'phone' => '+855 12 345 678',
            ])
            ->assignRole(Role::SuperAdmin->value);

        User::factory()
            ->create([
                'name' => 'Store Manager',
                'email' => 'manager@example.com',
            ])
            ->assignRole(Role::Manager->value);

        User::factory()
            ->create([
                'name' => 'Test Customer',
                'email' => 'customer@example.com',
            ])
            ->assignRole(Role::Customer->value);
    }
}
