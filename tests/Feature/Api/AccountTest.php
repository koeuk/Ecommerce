<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->customer = tap(User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]))->assignRole(Role::Customer->value);
    }

    public function test_the_account_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/account/orders')->assertUnauthorized();
        $this->getJson('/api/v1/wishlist')->assertUnauthorized();
        $this->putJson('/api/v1/account/profile')->assertUnauthorized();
    }

    // Order history

    public function test_order_history_returns_only_the_customers_own_orders(): void
    {
        Order::factory()->count(2)->create(['user_id' => $this->customer->id]);
        Order::factory()->create();                       // somebody else
        Order::factory()->guest()->create();              // a guest order

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/account/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_order_history_is_newest_first(): void
    {
        $older = Order::factory()->placedAt(now()->subDays(5))->create(['user_id' => $this->customer->id]);
        $newer = Order::factory()->placedAt(now())->create(['user_id' => $this->customer->id]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/account/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_number', $newer->order_number)
            ->assertJsonPath('data.1.order_number', $older->order_number);
    }

    // Profile

    public function test_a_customer_can_update_their_profile(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/account/profile', [
                'name' => 'Sok Dara',
                'email' => $this->customer->email,
                'phone' => '012 999 888',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sok Dara');

        $this->assertSame('012 999 888', $this->customer->fresh()->phone);
    }

    public function test_changing_the_email_clears_verification(): void
    {
        $this->customer->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/account/profile', [
                'name' => $this->customer->name,
                'email' => 'new@example.com',
            ])
            ->assertOk();

        $this->assertNull($this->customer->fresh()->email_verified_at);
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/account/profile', [
                'name' => 'Sok Dara',
                'email' => 'taken@example.com',
            ])
            ->assertJsonValidationErrors('email');
    }

    // Password

    public function test_a_customer_can_change_their_password(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/account/password', [
                'current_password' => 'Password123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPassword456!', $this->customer->fresh()->password));
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('Password123!', $this->customer->fresh()->password));
    }

    public function test_changing_the_password_signs_other_devices_out(): void
    {
        $other = $this->customer->createToken('other-device');
        $current = $this->customer->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->putJson('/api/v1/account/password', [
                'current_password' => 'Password123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertOk();

        // The device that made the change stays signed in; the other does not.
        $this->assertSame(1, $this->customer->fresh()->tokens()->count());
        $this->assertNull($this->customer->tokens()->find($other->accessToken->id));
    }

    // Cancelling an order

    public function test_a_customer_can_cancel_their_own_pending_order(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->forProduct($product)
            ->create(['stock_quantity' => 7]);

        $order = Order::factory()->create(['user_id' => $this->customer->id]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_variant_id' => $variant->id, 'product_name' => 'Laptop',
            'sku' => 'A-1', 'unit_price' => 100, 'quantity' => 3, 'subtotal' => 300,
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/account/orders/{$order->order_number}/cancel")
            ->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status->value);

        // Cancelling puts the goods back on the shelf, same as the admin path.
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_a_customer_cannot_cancel_a_shipped_order(): void
    {
        $order = Order::factory()->shipped()->create(['user_id' => $this->customer->id]);

        // OrderStatus permits shipped -> delivered only.
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/account/orders/{$order->order_number}/cancel")
            ->assertJsonValidationErrors('order');

        $this->assertSame('shipped', $order->fresh()->status->value);
    }

    public function test_a_customer_cannot_cancel_somebody_elses_order(): void
    {
        $order = Order::factory()->create();   // a different customer

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/account/orders/{$order->order_number}/cancel")
            ->assertNotFound();

        $this->assertSame('pending', $order->fresh()->status->value);
    }

    // Wishlist

    public function test_a_product_can_be_added_to_the_wishlist(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertCreated();

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_adding_the_same_product_twice_is_harmless(): void
    {
        $product = Product::factory()->create();

        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->customer, 'sanctum')
                ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
                ->assertCreated();
        }

        // The table is unique on (user_id, product_id).
        $this->assertSame(1, Wishlist::count());
    }

    public function test_the_wishlist_lists_published_products_only(): void
    {
        $published = Product::factory()->create();
        $draft = Product::factory()->draft()->create();

        foreach ([$published, $draft] as $product) {
            Wishlist::create(['user_id' => $this->customer->id, 'product_id' => $product->id]);
        }

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_a_product_can_be_removed_from_the_wishlist(): void
    {
        $product = Product::factory()->create();
        Wishlist::create(['user_id' => $this->customer->id, 'product_id' => $product->id]);

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/wishlist/{$product->id}")
            ->assertOk();

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_one_customer_cannot_see_anothers_wishlist(): void
    {
        $other = tap(User::factory()->create())->assignRole(Role::Customer->value);
        $product = Product::factory()->create();

        Wishlist::create(['user_id' => $other->id, 'product_id' => $product->id]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Nor delete from it.
        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/wishlist/{$product->id}")
            ->assertOk();

        $this->assertDatabaseCount('wishlists', 1);
    }
}
