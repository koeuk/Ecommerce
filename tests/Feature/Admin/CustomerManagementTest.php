<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    private function customer(array $attributes = []): User
    {
        return tap(User::factory()->create($attributes))->assignRole(Role::Customer->value);
    }

    // Listing

    public function test_the_customer_list_is_reachable(): void
    {
        $this->customer();
        $this->customer();
        $this->customer();

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Index')
                ->count('customers.data', 3)
            );
    }

    public function test_staff_accounts_are_not_listed_as_customers(): void
    {
        $this->customer(['name' => 'Sok Dara']);
        tap(User::factory()->create())->assignRole(Role::Staff->value);

        // The manager from setUp shares the users table too — only the one
        // customer-role account may appear.
        $this->actingAs($this->manager)
            ->get(route('admin.customers.index'))
            ->assertInertia(fn ($page) => $page
                ->count('customers.data', 1)
                ->where('customers.data.0.name', 'Sok Dara')
            );
    }

    public function test_customers_can_be_searched_and_filtered_by_status(): void
    {
        $this->customer(['name' => 'Sok Dara']);
        $this->customer(['name' => 'Someone Else', 'is_active' => false]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['filter' => ['search' => 'Sok']]))
            ->assertInertia(fn ($page) => $page
                ->count('customers.data', 1)
                ->where('customers.data.0.name', 'Sok Dara')
            );

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index', ['filter' => ['status' => 'inactive']]))
            ->assertInertia(fn ($page) => $page
                ->count('customers.data', 1)
                ->where('customers.data.0.name', 'Someone Else')
            );
    }

    public function test_lifetime_value_counts_only_paid_orders(): void
    {
        $customer = $this->customer();

        Order::factory()->paid()->withTotals(100)->create(['user_id' => $customer->id]);
        Order::factory()->withTotals(55)->create(['user_id' => $customer->id]);   // unpaid

        $this->actingAs($this->manager)
            ->get(route('admin.customers.index'))
            ->assertInertia(fn ($page) => $page
                ->where('customers.data.0.orders_count', 2)
                ->where('customers.data.0.lifetime_value', 100)
            );
    }

    // Detail

    public function test_the_detail_page_shows_profile_stats_and_orders(): void
    {
        $customer = $this->customer();

        Order::factory()->paid()->withTotals(100)->create(['user_id' => $customer->id]);
        Order::factory()->paid()->withTotals(50)->create(['user_id' => $customer->id]);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Show')
                ->where('customer.id', $customer->id)
                ->where('stats.orders_count', 2)
                ->where('stats.lifetime_value', 150)
                ->where('stats.average_order', 75)
                ->count('orders', 2)
            );
    }

    public function test_an_admin_account_is_not_found_as_a_customer(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);

        $this->actingAs($this->manager)
            ->get(route('admin.customers.show', $staff))
            ->assertNotFound();
    }

    // Activation

    public function test_a_customer_account_can_be_deactivated_and_reactivated(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->manager)
            ->put(route('admin.customers.active', $customer))
            ->assertRedirect();

        $this->assertFalse($customer->fresh()->is_active);

        $this->actingAs($this->manager)
            ->put(route('admin.customers.active', $customer))
            ->assertRedirect();

        $this->assertTrue($customer->fresh()->is_active);
    }

    // Permissions

    public function test_staff_may_view_but_not_update(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);
        $customer = $this->customer();

        $this->actingAs($staff)->get(route('admin.customers.index'))->assertOk();

        $this->actingAs($staff)
            ->put(route('admin.customers.active', $customer))
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_customers_cannot_reach_the_customer_admin(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('admin.customers.index'))
            ->assertRedirect();
    }
}
