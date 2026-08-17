<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
        $this->staff = tap(User::factory()->create())->assignRole(Role::Staff->value);
        $this->customer = tap(User::factory()->create())->assignRole(Role::Customer->value);
    }

    // Product

    public function test_product_policy_follows_permissions(): void
    {
        $product = Product::factory()->create();

        $this->assertTrue($this->manager->can('update', $product));
        $this->assertTrue($this->manager->can('delete', $product));

        $this->assertTrue($this->staff->can('update', $product));   // staff may edit
        $this->assertFalse($this->staff->can('delete', $product));  // but not delete

        $this->assertFalse($this->customer->can('view', $product));
    }

    public function test_a_product_referenced_by_an_order_cannot_be_force_deleted(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Snapshot',
            'sku' => 'SNAP-1',
            'unit_price' => 10,
            'quantity' => 1,
            'subtotal' => 10,
        ]);

        // Hard-deleting would orphan the order line, so it is refused even
        // for a user who holds 'delete product'.
        $this->assertFalse($this->manager->can('forceDelete', $product));

        $untouched = Product::factory()->create();
        $this->assertTrue($this->manager->can('forceDelete', $untouched));
    }

    public function test_super_admin_bypasses_every_policy(): void
    {
        $superAdmin = tap(User::factory()->create())->assignRole(Role::SuperAdmin->value);
        $product = Product::factory()->create();

        $this->assertTrue($superAdmin->can('delete', $product));
        $this->assertTrue($superAdmin->can('update', Order::factory()->create()));
    }

    // Order

    public function test_a_customer_may_view_only_their_own_order(): void
    {
        $mine = Order::factory()->create(['user_id' => $this->customer->id]);
        $theirs = Order::factory()->create();

        $this->assertTrue($this->customer->can('view', $mine));
        $this->assertFalse($this->customer->can('view', $theirs));

        // An admin with the permission sees any order.
        $this->assertTrue($this->manager->can('view', $theirs));
    }

    public function test_a_paid_order_cannot_be_deleted(): void
    {
        $paid = Order::factory()->paid()->create();
        $unpaid = Order::factory()->create();

        $this->assertFalse($this->manager->can('delete', $paid));
        $this->assertTrue($this->manager->can('delete', $unpaid));
    }

    public function test_orders_are_never_force_deleted(): void
    {
        $superAdmin = tap(User::factory()->create())->assignRole(Role::SuperAdmin->value);
        $order = Order::factory()->create();

        // Gate::before still short-circuits for super admin — the deny only
        // binds users who actually reach the policy.
        $this->assertTrue($superAdmin->can('forceDelete', $order));
        $this->assertFalse($this->manager->can('forceDelete', $order));
    }

    public function test_a_status_transition_must_be_legal(): void
    {
        $delivered = Order::factory()->delivered()->create();
        $pending = Order::factory()->create();

        // OrderStatus owns the transition rules; the policy defers to them.
        $this->assertTrue($this->manager->can('transitionTo', [$pending, OrderStatus::Confirmed]));
        $this->assertFalse($this->manager->can('transitionTo', [$delivered, OrderStatus::Pending]));
    }

    // Review

    public function test_an_author_may_edit_their_pending_review_only(): void
    {
        // reviews is unique on (product_id, user_id) — one review per product.
        $pending = Review::create([
            'product_id' => Product::factory()->create()->id, 'user_id' => $this->customer->id,
            'rating' => 5, 'body' => 'Great', 'status' => 'pending',
        ]);

        $approved = Review::create([
            'product_id' => Product::factory()->create()->id, 'user_id' => $this->customer->id,
            'rating' => 4, 'body' => 'Good', 'status' => 'approved',
        ]);

        $this->assertTrue($this->customer->can('update', $pending));
        $this->assertFalse($this->customer->can('update', $approved));

        // Moderation is an admin action regardless of authorship.
        $this->assertTrue($this->staff->can('moderate', $approved));
        $this->assertFalse($this->customer->can('moderate', $approved));
    }

    public function test_approved_reviews_are_publicly_viewable(): void
    {
        $other = User::factory()->create();

        $approved = Review::create([
            'product_id' => Product::factory()->create()->id, 'user_id' => $other->id,
            'rating' => 5, 'body' => 'Great', 'status' => 'approved',
        ]);

        $pending = Review::create([
            'product_id' => Product::factory()->create()->id, 'user_id' => $other->id,
            'rating' => 1, 'body' => 'Bad', 'status' => 'pending',
        ]);

        $this->assertTrue($this->customer->can('view', $approved));
        $this->assertFalse($this->customer->can('view', $pending));
    }

    // Coupon

    public function test_a_redeemed_coupon_cannot_be_deleted(): void
    {
        $used = Coupon::create([
            'code' => 'USED10', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);
        $used->usages()->create([
            'user_id' => $this->customer->id,
            'order_id' => Order::factory()->create()->id,
            'discount_amount' => 5,
        ]);

        $unused = Coupon::create([
            'code' => 'FRESH10', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);

        // Order history references it, so deactivate rather than delete.
        $this->assertFalse($this->manager->can('delete', $used));
        $this->assertTrue($this->manager->can('delete', $unused));
    }

    public function test_staff_cannot_manage_coupons(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);

        $this->assertFalse($this->staff->can('view', $coupon));
        $this->assertFalse($this->staff->can('update', $coupon));
        $this->assertTrue($this->manager->can('update', $coupon));
    }
}
