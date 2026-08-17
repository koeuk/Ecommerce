<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceDataSeeder::class);   // shipping zones + methods
    }

    private function variant(int $stock = 10, float $price = 100): ProductVariant
    {
        $product = Product::factory()->create(['price' => $price]);

        return ProductVariant::factory()->forProduct($product)->create([
            'stock_quantity' => $stock,
            'price' => $price,
        ]);
    }

    /** Adds one line to a guest cart and returns its token. */
    private function guestCart(ProductVariant $variant, int $quantity = 1): string
    {
        return $this->postJson('/api/v1/cart', [
            'variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->json('cart_token');
    }

    private function address(string $province = 'Phnom Penh'): array
    {
        return [
            'receiver_name' => 'Sok Dara',
            'phone' => '012 345 678',
            'address_line1' => '12 Street 240',
            'city' => 'Phnom Penh',
            'state' => $province,
            'country_code' => 'KH',
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Sok Dara',
            'customer_email' => 'sok@example.com',
            'customer_phone' => '012 345 678',
            'shipping_address' => $this->address(),
        ], $overrides);
    }

    // Quote

    public function test_a_quote_returns_totals_and_shipping_options(): void
    {
        $variant = $this->variant(price: 50);
        $token = $this->guestCart($variant, 2);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout/quote', ['province' => 'Phnom Penh'])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.shipping_zone', 'Phnom Penh')
            ->assertJsonCount(1, 'data.shipping_methods');
    }

    public function test_an_unknown_province_falls_back_to_the_default_zone(): void
    {
        $variant = $this->variant(price: 10);
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout/quote', ['province' => 'Nowhere'])
            ->assertOk()
            ->assertJsonPath('data.shipping_zone', 'Provinces');
    }

    public function test_the_free_shipping_threshold_is_honoured(): void
    {
        $variant = $this->variant(price: 200);        // above the $100 threshold
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout/quote', ['province' => 'Phnom Penh'])
            ->assertOk()
            ->assertJsonPath('data.shipping_fee', 0);
    }

    public function test_shipping_is_charged_below_the_threshold(): void
    {
        $variant = $this->variant(price: 20);
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout/quote', ['province' => 'Phnom Penh'])
            ->assertOk()
            ->assertJsonPath('data.shipping_fee', 1.5)
            ->assertJsonPath('data.grand_total', 21.5);
    }

    // Placing an order

    public function test_a_guest_can_place_an_order(): void
    {
        $variant = $this->variant(stock: 10, price: 50);
        $token = $this->guestCart($variant, 2);

        $response = $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonCount(1, 'data.items');

        $order = Order::firstWhere('order_number', $response->json('data.order_number'));

        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
    }

    public function test_the_order_number_is_sequential_per_day(): void
    {
        $today = now()->format('Ymd');

        foreach ([1, 2] as $ignored) {
            $variant = $this->variant();
            $token = $this->guestCart($variant);
            $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', $this->payload());
        }

        $numbers = Order::orderBy('id')->pluck('order_number')->all();

        $this->assertSame(["ORD-{$today}-0001", "ORD-{$today}-0002"], $numbers);
    }

    public function test_placing_an_order_decrements_stock_and_records_the_movement(): void
    {
        $variant = $this->variant(stock: 10);
        $token = $this->guestCart($variant, 3);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertCreated();

        $this->assertSame(7, $variant->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'out',
            'quantity' => -3,
            'stock_after' => 7,
        ]);
    }

    public function test_the_cart_is_emptied_once_the_order_is_placed(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant, 2);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertCreated();

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_order_lines_snapshot_the_product_details(): void
    {
        $product = Product::factory()->create(['title' => ['en' => 'Gaming Laptop']]);
        $variant = ProductVariant::factory()->forProduct($product)
            ->create(['stock_quantity' => 5, 'price' => 999, 'sku' => 'LAP-A', 'label' => '16GB']);

        $token = $this->guestCart($variant);
        $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', $this->payload());

        // Renaming the product must not rewrite what was ordered.
        $product->update(['title' => ['en' => 'Renamed Later']]);

        $this->assertDatabaseHas('order_items', [
            'product_name' => 'Gaming Laptop',
            'sku' => 'LAP-A',
            'variant_label' => '16GB',
            'unit_price' => 999.00,
        ]);
    }

    public function test_the_address_is_stored_as_a_snapshot(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', $this->payload());

        $order = Order::first();

        $this->assertSame('12 Street 240', $order->shipping_address['address_line1']);
        $this->assertSame('Phnom Penh', $order->shipping_address['state']);
        // Billing defaults to shipping when not supplied.
        $this->assertSame($order->shipping_address, $order->billing_address);
    }

    public function test_a_status_history_row_is_written(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/checkout', $this->payload());

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => Order::first()->id,
            'to_status' => 'pending',
        ]);
    }

    public function test_a_signed_in_customer_order_is_attributed_to_them(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertCreated();

        $this->assertSame($user->id, Order::first()->user_id);
    }

    // Guards

    public function test_an_empty_cart_cannot_be_checked_out(): void
    {
        $this->withHeader('X-Cart-Token', 'nothing-here')
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_an_address_and_phone(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', ['customer_name' => 'Sok Dara'])
            ->assertJsonValidationErrors(['customer_phone', 'shipping_address']);
    }

    public function test_stock_taken_since_the_cart_was_filled_blocks_checkout(): void
    {
        $variant = $this->variant(stock: 5);
        $token = $this->guestCart($variant, 5);

        // Someone else bought them in the meantime.
        $variant->update(['stock_quantity' => 1]);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertJsonValidationErrors('cart');

        // Nothing was half-created, and stock was left alone.
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }

    public function test_the_client_cannot_dictate_the_total(): void
    {
        // Below the $100 free-shipping threshold, so a fee genuinely applies.
        $variant = $this->variant(price: 20);
        $token = $this->guestCart($variant, 1);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload([
                'grand_total' => 1,
                'subtotal' => 1,
                'shipping_fee' => 0,
            ]))
            ->assertCreated();

        // The server recomputed everything; the injected figures were ignored.
        $order = Order::first();
        $this->assertSame('20.00', $order->subtotal);
        $this->assertSame('21.50', $order->grand_total);
    }

    public function test_placing_an_order_records_a_payment_row_via_the_gateway(): void
    {
        $variant = $this->variant(price: 50);
        $token = $this->guestCart($variant, 2);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->assertCreated();

        $order = Order::first();

        // Checkout goes through GatewayRegistry, so every order has a payment
        // history from the moment it is placed.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            // 2 x $50 hits the free-shipping threshold, so this is the total.
            'amount' => 100.00,
            'status' => 'unpaid',
        ]);
    }

    public function test_an_unconfigured_payment_method_is_rejected(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['payment_method' => 'stripe']))
            ->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cod_can_be_requested_explicitly(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['payment_method' => 'cod']))
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['gateway' => 'cod']);
    }

    // Coupons

    public function test_a_valid_coupon_reduces_the_total(): void
    {
        Coupon::create([
            'code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);

        $variant = $this->variant(price: 200);
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'SAVE10']))
            ->assertCreated()
            ->assertJsonPath('data.discount_total', 20)
            ->assertJsonPath('data.grand_total', 180);

        $this->assertDatabaseHas('coupon_usages', ['discount_amount' => 20.00]);
        $this->assertSame(1, Coupon::first()->used_count);
    }

    public function test_an_invalid_coupon_stops_checkout_rather_than_being_ignored(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'NOPE']))
            ->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseCount('orders', 0);
    }

    // Tracking

    public function test_a_guest_can_track_an_order_with_the_matching_contact(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);
        $number = $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->json('data.order_number');

        $this->getJson("/api/v1/orders/{$number}?contact=sok@example.com")
            ->assertOk()
            ->assertJsonPath('data.order_number', $number);
    }

    public function test_the_order_number_alone_is_not_enough_to_track(): void
    {
        $variant = $this->variant();
        $token = $this->guestCart($variant);
        $number = $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload())
            ->json('data.order_number');

        // Otherwise the sequence could simply be walked.
        $this->getJson("/api/v1/orders/{$number}")->assertNotFound();
        $this->getJson("/api/v1/orders/{$number}?contact=wrong@example.com")->assertNotFound();
    }

    public function test_a_customer_can_track_their_own_order_without_a_contact(): void
    {
        $user = tap(User::factory()->create())->assignRole(Role::Customer->value);
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $number = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->payload())
            ->json('data.order_number');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$number}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $number);
    }
}
