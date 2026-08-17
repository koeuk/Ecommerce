<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for the coupon bugs found in the audit: a start date that
 * was never checked, and a per-customer limit that was never enforced.
 */
class CouponRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceDataSeeder::class);
    }

    private function variant(float $price = 200): ProductVariant
    {
        $product = Product::factory()->create(['price' => $price]);

        return ProductVariant::factory()->forProduct($product)
            ->create(['stock_quantity' => 50, 'price' => $price]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Sok Dara',
            'customer_email' => 'sok@example.com',
            'customer_phone' => '012 345 678',
            'shipping_address' => [
                'receiver_name' => 'Sok Dara', 'phone' => '012 345 678',
                'address_line1' => '12 Street 240', 'city' => 'Phnom Penh',
                'state' => 'Phnom Penh', 'country_code' => 'KH',
            ],
        ], $overrides);
    }

    private function guestCartToken(ProductVariant $variant, int $quantity = 1): string
    {
        return $this->postJson('/api/v1/cart', [
            'variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->json('cart_token');
    }

    private function customer(): User
    {
        return tap(User::factory()->create())->assignRole(Role::Customer->value);
    }

    // Start date

    public function test_a_coupon_that_has_not_started_is_refused(): void
    {
        Coupon::create([
            'code' => 'FUTURE', 'type' => 'percent', 'value' => 50,
            'is_active' => true, 'starts_at' => now()->addMonth(),
        ]);

        $token = $this->guestCartToken($this->variant());

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'FUTURE']))
            ->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_coupon_works_once_its_start_date_passes(): void
    {
        Coupon::create([
            'code' => 'STARTED', 'type' => 'percent', 'value' => 10,
            'is_active' => true, 'starts_at' => now()->subDay(),
        ]);

        $token = $this->guestCartToken($this->variant(price: 200));

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'STARTED']))
            ->assertCreated()
            ->assertJsonPath('data.discount_total', 20);
    }

    public function test_the_quote_surfaces_the_start_date_error(): void
    {
        Coupon::create([
            'code' => 'FUTURE', 'type' => 'percent', 'value' => 50,
            'is_active' => true, 'starts_at' => now()->addMonth(),
        ]);

        $token = $this->guestCartToken($this->variant());

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'FUTURE'])
            ->assertOk()
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.coupon', null)
            ->assertJsonPath('data.coupon_error', 'That coupon is not active yet.');
    }

    // Per-customer limit

    public function test_a_customer_cannot_exceed_the_per_user_limit(): void
    {
        Coupon::create([
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 5,
            'is_active' => true, 'per_user_limit' => 1,
        ]);

        $customer = $this->customer();

        // First redemption succeeds.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $this->variant()->id, 'quantity' => 1]);
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONCE']))
            ->assertCreated();

        // Second is refused.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $this->variant()->id, 'quantity' => 1]);
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONCE']))
            ->assertJsonValidationErrors('coupon_code');

        $this->assertSame(1, CouponUsage::count());
        $this->assertSame(1, Order::count());
    }

    public function test_the_per_user_limit_is_per_customer_not_global(): void
    {
        Coupon::create([
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 5,
            'is_active' => true, 'per_user_limit' => 1,
        ]);

        foreach ([$this->customer(), $this->customer()] as $customer) {
            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/v1/cart', ['variant_id' => $this->variant()->id, 'quantity' => 1]);

            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONCE']))
                ->assertCreated();
        }

        $this->assertSame(2, CouponUsage::count());
    }

    // Global limit

    public function test_the_global_usage_limit_still_holds(): void
    {
        Coupon::create([
            'code' => 'ONLYONE', 'type' => 'fixed', 'value' => 5,
            'is_active' => true, 'usage_limit' => 1,
        ]);

        $first = $this->guestCartToken($this->variant());
        $this->withHeader('X-Cart-Token', $first)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONLYONE']))
            ->assertCreated();

        $second = $this->guestCartToken($this->variant());
        $this->withHeader('X-Cart-Token', $second)
            ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONLYONE']))
            ->assertJsonValidationErrors('coupon_code');

        $this->assertSame(1, Order::count());
    }

    public function test_an_expired_or_inactive_coupon_is_refused(): void
    {
        Coupon::create([
            'code' => 'GONE', 'type' => 'fixed', 'value' => 5,
            'is_active' => true, 'expires_at' => now()->subDay(),
        ]);
        Coupon::create([
            'code' => 'OFF', 'type' => 'fixed', 'value' => 5, 'is_active' => false,
        ]);

        foreach (['GONE', 'OFF'] as $code) {
            $token = $this->guestCartToken($this->variant());

            $this->withHeader('X-Cart-Token', $token)
                ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => $code]))
                ->assertJsonValidationErrors('coupon_code');
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guests_are_not_blocked_by_a_per_user_limit(): void
    {
        // Guests have no account to count against, so the limit cannot apply.
        Coupon::create([
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 5,
            'is_active' => true, 'per_user_limit' => 1,
        ]);

        foreach ([1, 2] as $ignored) {
            $token = $this->guestCartToken($this->variant());

            $this->withHeader('X-Cart-Token', $token)
                ->postJson('/api/v1/checkout', $this->payload(['coupon_code' => 'ONCE']))
                ->assertCreated();
        }

        $this->assertSame(2, Order::count());
    }
}
