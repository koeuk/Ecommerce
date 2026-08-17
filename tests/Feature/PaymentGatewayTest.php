<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\CodGateway;
use App\Payments\GatewayRegistry;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_is_registered_and_available(): void
    {
        $registry = app(GatewayRegistry::class);

        $this->assertCount(1, $registry->available());
        $this->assertInstanceOf(CodGateway::class, $registry->get('cod'));
        $this->assertSame([['key' => 'cod', 'label' => 'Cash on Delivery']], $registry->options());
    }

    public function test_an_unknown_gateway_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GatewayRegistry::class)->get('stripe');
    }

    public function test_initiating_cod_records_a_pending_payment(): void
    {
        $order = Order::factory()->create(['grand_total' => 125.50]);

        $result = app(CodGateway::class)->initiate($order);

        $this->assertInstanceOf(PaymentGateway::class, app(CodGateway::class));

        // Nothing to redirect to — the money is collected offline.
        $this->assertArrayNotHasKey('redirect_url', $result);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            'amount' => 125.50,
            'status' => PaymentStatus::Unpaid->value,
        ]);
    }

    public function test_cod_has_no_webhook(): void
    {
        $this->assertNull(app(CodGateway::class)->handleWebhook(request()));
    }

    public function test_the_settings_endpoint_advertises_the_available_methods(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.payment_methods.0.key', 'cod');
    }

    public function test_an_unavailable_gateway_is_not_offered(): void
    {
        // A half-configured gateway must not reach the checkout screen.
        config(['payments.gateways' => [CodGateway::class, UnconfiguredTestGateway::class]]);
        app()->forgetInstance(GatewayRegistry::class);

        $registry = new GatewayRegistry(config('payments.gateways'));

        $this->assertCount(2, $registry->all());
        $this->assertCount(1, $registry->available());
        $this->assertSame('cod', $registry->available()[0]->key());
    }
}

/** Stands in for a gateway whose credentials are missing. */
class UnconfiguredTestGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'unconfigured';
    }

    public function label(): string
    {
        return 'Unconfigured';
    }

    public function initiate(Order $order): array
    {
        return [];
    }

    public function handleWebhook(Request $request): ?Payment
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
