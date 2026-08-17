<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function variant(int $stock = 10, float $price = 100): ProductVariant
    {
        $product = Product::factory()->create(['price' => $price]);

        return ProductVariant::factory()->forProduct($product)->create([
            'stock_quantity' => $stock,
            'price' => $price,
        ]);
    }

    private function customer(): User
    {
        return tap(User::factory()->create())->assignRole(Role::Customer->value);
    }

    // Guest carts

    public function test_a_guest_adding_an_item_is_issued_a_cart_token(): void
    {
        $variant = $this->variant();

        $response = $this->postJson('/api/v1/cart', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', 200);

        $token = $response->json('cart_token');

        $this->assertNotNull($token);
        $response->assertHeader('X-Cart-Token', $token);
        $this->assertDatabaseHas('cart_items', ['session_id' => $token, 'quantity' => 2]);
    }

    public function test_a_guest_cart_is_retrieved_with_its_token(): void
    {
        $variant = $this->variant();

        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->json('cart_token');

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 1);
    }

    public function test_one_guest_cannot_see_another_guests_cart(): void
    {
        $variant = $this->variant();

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->withHeader('X-Cart-Token', 'some-other-token')
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_a_guest_without_a_token_gets_an_empty_cart(): void
    {
        $variant = $this->variant();
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);

        // Must not fall through to every row with a null session_id.
        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);
    }

    // Adding

    public function test_adding_the_same_variant_twice_tops_up_one_line(): void
    {
        $variant = $this->variant();

        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])
            ->json('cart_token');

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.line_count', 1)
            ->assertJsonPath('data.item_count', 5);
    }

    public function test_adding_more_than_stock_is_rejected(): void
    {
        $variant = $this->variant(stock: 3);

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 5])
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_topping_up_beyond_stock_is_rejected(): void
    {
        $variant = $this->variant(stock: 5);

        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 4])
            ->json('cart_token');

        // 4 already in the cart, so 2 more would exceed the 5 available.
        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(4, CartItem::first()->quantity);
    }

    public function test_a_backorderable_variant_ignores_the_stock_limit(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->forProduct($product)->backorderable()
            ->create(['stock_quantity' => 0, 'price' => 50]);

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 5])
            ->assertCreated()
            ->assertJsonPath('data.item_count', 5);
    }

    public function test_an_unpublished_product_cannot_be_added(): void
    {
        $product = Product::factory()->draft()->create();
        $variant = ProductVariant::factory()->forProduct($product)->create(['stock_quantity' => 10]);

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertJsonValidationErrors('variant_id');
    }

    public function test_an_inactive_variant_cannot_be_added(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->forProduct($product)->inactive()
            ->create(['stock_quantity' => 10]);

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertJsonValidationErrors('variant_id');
    }

    // Updating and removing

    public function test_a_line_quantity_can_be_changed(): void
    {
        $variant = $this->variant();
        $response = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $response->json('cart_token');
        $itemId = $response->json('data.items.0.id');

        $this->withHeader('X-Cart-Token', $token)
            ->patchJson("/api/v1/cart/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.item_count', 4);
    }

    public function test_setting_a_quantity_to_zero_removes_the_line(): void
    {
        $variant = $this->variant();
        $response = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $response->json('cart_token');
        $itemId = $response->json('data.items.0.id');

        $this->withHeader('X-Cart-Token', $token)
            ->patchJson("/api/v1/cart/{$itemId}", ['quantity' => 0])
            ->assertOk()
            ->assertJsonPath('data.line_count', 0);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_line_can_be_removed_and_the_cart_cleared(): void
    {
        $variant = $this->variant();
        $response = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = $response->json('cart_token');
        $itemId = $response->json('data.items.0.id');

        $this->withHeader('X-Cart-Token', $token)
            ->deleteJson("/api/v1/cart/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.line_count', 0);

        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->withHeader('X-Cart-Token', $token)
            ->deleteJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_another_guests_line_cannot_be_touched(): void
    {
        $variant = $this->variant();
        $itemId = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->json('data.items.0.id');

        $this->withHeader('X-Cart-Token', 'not-my-token')
            ->patchJson("/api/v1/cart/{$itemId}", ['quantity' => 99])
            ->assertNotFound();

        $this->assertSame(1, CartItem::find($itemId)->quantity);
    }

    // Signed-in carts

    public function test_a_signed_in_cart_keys_off_the_user(): void
    {
        $user = $this->customer();
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('cart_token', null);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'session_id' => null,
            'quantity' => 2,
        ]);
    }

    // Stale carts

    public function test_a_price_change_is_surfaced_on_the_line(): void
    {
        $variant = $this->variant(price: 100);
        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->json('cart_token');

        $variant->update(['price' => 120]);

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.price_at_add', 100)
            ->assertJsonPath('data.items.0.unit_price', 120)
            ->assertJsonPath('data.items.0.price_changed', true)
            ->assertJsonPath('data.has_issues', true)
            // Totals follow the current price, not the price at add.
            ->assertJsonPath('data.subtotal', 120);
    }

    public function test_stock_running_out_is_surfaced_on_the_line(): void
    {
        $variant = $this->variant(stock: 5);
        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 5])
            ->json('cart_token');

        $variant->update(['stock_quantity' => 2]);

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.in_stock', false)
            ->assertJsonPath('data.has_issues', true);
    }

    // The merge — the part that is easy to get wrong

    public function test_a_guest_cart_survives_login_without_duplicating(): void
    {
        $user = $this->customer();
        $a = $this->variant();
        $b = $this->variant();

        // Guest adds two lines.
        $token = $this->postJson('/api/v1/cart', ['variant_id' => $a->id, 'quantity' => 2])
            ->json('cart_token');
        $this->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart', ['variant_id' => $b->id, 'quantity' => 1]);

        // Signs in and merges.
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart/merge')
            ->assertOk()
            ->assertJsonPath('data.line_count', 2)
            ->assertJsonPath('data.item_count', 3);

        // The rows moved across rather than being copied.
        $this->assertSame(2, CartItem::forUser($user->id)->count());
        $this->assertSame(0, CartItem::whereNotNull('session_id')->count());
    }

    public function test_merging_sums_quantities_for_a_shared_variant(): void
    {
        $user = $this->customer();
        $variant = $this->variant(stock: 10);

        // Guest first — actingAs persists for the rest of the test, so the
        // unauthenticated call has to come before it.
        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 3])
            ->json('cart_token');

        // Same variant already in the user's own cart.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart/merge')
            ->assertOk()
            ->assertJsonPath('data.line_count', 1)
            ->assertJsonPath('data.item_count', 5);
    }

    public function test_merging_clamps_a_combined_quantity_to_available_stock(): void
    {
        $user = $this->customer();
        $variant = $this->variant(stock: 4);

        // Guest first — actingAs persists for the rest of the test.
        $token = $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 3])
            ->json('cart_token');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 3]);

        // 3 + 3 = 6 but only 4 exist, so the merge cannot create an
        // unfulfillable line.
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Cart-Token', $token)
            ->postJson('/api/v1/cart/merge')
            ->assertOk()
            ->assertJsonPath('data.item_count', 4);
    }

    public function test_merging_without_a_token_is_a_no_op(): void
    {
        $user = $this->customer();
        $variant = $this->variant();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/merge')
            ->assertOk()
            ->assertJsonPath('data.item_count', 1);
    }

    public function test_merging_requires_authentication(): void
    {
        $this->postJson('/api/v1/cart/merge')->assertUnauthorized();
    }
}
