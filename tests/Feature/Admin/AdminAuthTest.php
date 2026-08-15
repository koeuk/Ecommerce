<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(Role $role, array $attributes = []): User
    {
        return tap(User::factory()->create($attributes))
            ->assignRole($role->value);
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_admin_can_authenticate(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_records_the_last_login_timestamp(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin, ['last_login_at' => null]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    public function test_customer_cannot_authenticate_as_admin(): void
    {
        $customer = $this->userWithRole(Role::Customer);

        $this->post(route('admin.login.post'), [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        // The session must be dropped again, not left authenticated.
        $this->assertGuest();
    }

    public function test_deactivated_admin_cannot_authenticate(): void
    {
        $admin = $this->userWithRole(Role::Manager, ['is_active' => false]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_cannot_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::Customer))
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('home'));
    }

    public function test_staff_can_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::Staff))
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_authenticated_admin_is_redirected_away_from_the_login_screen(): void
    {
        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_logout(): void
    {
        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.post'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
