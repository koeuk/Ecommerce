<?php

namespace App\Payments;

use InvalidArgumentException;

/**
 * Resolves a gateway by key and lists what the storefront may offer.
 *
 * Adding ABA PayWay or Bakong KHQR means writing a PaymentGateway
 * implementation and appending it to config('payments.gateways') — nothing
 * in checkout changes.
 */
class GatewayRegistry
{
    /** @param array<class-string<PaymentGateway>> $gateways */
    public function __construct(private readonly array $gateways) {}

    public function get(string $key): PaymentGateway
    {
        foreach ($this->all() as $gateway) {
            if ($gateway->key() === $key) {
                return $gateway;
            }
        }

        throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
    }

    /** @return array<PaymentGateway> */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), $this->gateways);
    }

    /**
     * Only gateways that are actually configured — a half-configured gateway
     * must never appear at checkout.
     *
     * @return array<PaymentGateway>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (PaymentGateway $gateway) => $gateway->isAvailable(),
        ));
    }

    /** @return array<array{key: string, label: string}> */
    public function options(): array
    {
        return array_map(
            fn (PaymentGateway $g) => ['key' => $g->key(), 'label' => $g->label()],
            $this->available(),
        );
    }
}
