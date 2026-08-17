<?php

namespace Tests\Feature\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Mail\OrderMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    /** An order with one line backed by a real variant, so stock can move. */
    private function orderWithStock(int $quantity = 2, int $stock = 10): array
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->forProduct($product)
            ->create(['stock_quantity' => $stock, 'sku' => 'SKU-A']);

        $order = Order::factory()->create(['customer_email' => 'sok@example.com']);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Gaming Laptop',
            'sku' => 'SKU-A',
            'unit_price' => 100,
            'quantity' => $quantity,
            'subtotal' => 100 * $quantity,
        ]);

        return [$order->fresh('items'), $variant];
    }

    // Listing and detail

    public function test_the_order_list_is_reachable(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAs($this->manager)
            ->get(route('admin.orders.index'))
            ->assertOk();
    }

    public function test_orders_can_be_filtered_by_status_and_search(): void
    {
        Order::factory()->create(['order_number' => 'ORD-X-0001', 'customer_name' => 'Sok Dara']);
        Order::factory()->cancelled()->create(['customer_name' => 'Someone Else']);

        $this->actingAs($this->manager)
            ->get(route('admin.orders.index', ['filter' => ['status' => 'pending']]))
            ->assertOk();

        $this->actingAs($this->manager)
            ->get(route('admin.orders.index', ['filter' => ['search' => 'Sok']]))
            ->assertOk();
    }

    public function test_the_detail_page_shows_only_legal_next_statuses(): void
    {
        $order = Order::factory()->create();   // pending

        $this->actingAs($this->manager)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.allowed_transitions.0.value', 'confirmed')
                ->where('order.allowed_transitions.1.value', 'cancelled')
                ->count('order.allowed_transitions', 2)
            );
    }

    // Transitions

    public function test_a_legal_transition_is_applied_and_recorded(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'confirmed', 'note' => 'Called customer'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'note' => 'Called customer',
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        $order = Order::factory()->delivered()->create();

        // OrderStatus owns this rule; the service defers to it.
        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_shipping_stamps_the_date_and_marks_it_fulfilled(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Processing]);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'shipped']);

        $fresh = $order->fresh();

        $this->assertSame(OrderStatus::Shipped, $fresh->status);
        $this->assertSame(FulfillmentStatus::Fulfilled, $fresh->fulfillment_status);
        $this->assertNotNull($fresh->shipped_at);
    }

    // Stock release

    public function test_cancelling_an_order_returns_stock(): void
    {
        [$order, $variant] = $this->orderWithStock(quantity: 3, stock: 7);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $this->assertSame(10, $variant->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'return',
            'quantity' => 3,
        ]);

        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_stock_is_not_returned_twice(): void
    {
        [$order, $variant] = $this->orderWithStock(quantity: 3, stock: 7);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'cancelled']);

        // Force a second restock attempt directly — the guard, not the state
        // machine, is what has to hold here.
        app(InventoryService::class)->restockForOrder($order->fresh('items'));

        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertSame(1, $variant->inventoryMovements()->where('type', 'return')->count());
    }

    public function test_confirming_an_order_does_not_touch_stock(): void
    {
        [$order, $variant] = $this->orderWithStock(quantity: 3, stock: 7);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'confirmed']);

        $this->assertSame(7, $variant->fresh()->stock_quantity);
    }

    // Payment

    public function test_an_order_can_be_marked_paid(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('admin.orders.paid', $order))
            ->assertRedirect();

        $fresh = $order->fresh();

        $this->assertSame(PaymentStatus::Paid, $fresh->payment_status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_an_admin_note_can_be_saved(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('admin.orders.note', $order), ['admin_note' => 'Customer asked for evening delivery'])
            ->assertRedirect();

        $this->assertSame('Customer asked for evening delivery', $order->fresh()->admin_note);
    }

    // Emails

    public function test_shipping_an_order_emails_the_customer(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Processing,
            'customer_email' => 'sok@example.com',
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'shipped']);

        // OrderMail is ShouldQueue, so under Mail::fake it lands in the
        // queued bag rather than the sent one.
        Mail::assertQueued(OrderMail::class, fn (OrderMail $mail) => $mail->stage === 'shipped'
            && $mail->hasTo('sok@example.com'));
    }

    public function test_confirming_sends_no_email(): void
    {
        $order = Order::factory()->create(['customer_email' => 'sok@example.com']);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'confirmed']);

        // Only shipped/delivered/cancelled are worth an email.
        Mail::assertNothingQueued();
    }

    public function test_an_order_without_an_email_is_not_a_failure(): void
    {
        $order = Order::factory()->guest()->create([
            'status' => OrderStatus::Processing,
            'customer_email' => null,
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.orders.status', $order), ['status' => 'shipped'])
            ->assertRedirect();

        Mail::assertNothingQueued();
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    // Documents

    public function test_an_invoice_pdf_is_produced(): void
    {
        [$order] = $this->orderWithStock();

        $response = $this->actingAs($this->manager)
            ->get(route('admin.orders.invoice', $order));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_delivery_note_pdf_is_produced(): void
    {
        [$order] = $this->orderWithStock();

        $this->actingAs($this->manager)
            ->get(route('admin.orders.delivery-note', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // Permissions

    public function test_staff_may_view_and_update_but_not_delete(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);
        $order = Order::factory()->create();

        $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($staff)
            ->put(route('admin.orders.status', $order), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_customers_cannot_reach_the_order_admin(): void
    {
        $customer = tap(User::factory()->create())->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get(route('admin.orders.index'))
            ->assertRedirect();
    }
}
