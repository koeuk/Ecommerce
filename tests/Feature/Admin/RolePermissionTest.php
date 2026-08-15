<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_passes_every_permission_check(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::SuperAdmin->value);

        // Granted via Gate::before, without holding any permission rows.
        $this->assertTrue($user->can('delete product'));
        $this->assertTrue($user->can('update setting'));
        $this->assertTrue($user->can('a permission that does not exist'));
    }

    public function test_manager_cannot_administer_roles(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Manager->value);

        $this->assertTrue($user->can('update product'));
        $this->assertFalse($user->can('update role'));
        $this->assertFalse($user->can('delete user'));
    }

    public function test_staff_has_read_mostly_access(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Staff->value);

        $this->assertTrue($user->can('view order'));
        $this->assertTrue($user->can('update order'));
        $this->assertFalse($user->can('delete order'));
        $this->assertFalse($user->can('create product'));
    }

    public function test_customer_is_not_an_admin(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->can('view order'));
    }
}
