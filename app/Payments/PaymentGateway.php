<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * The seam between checkout and whatever takes the money.
 *
 * Checkout never names a gateway — it asks the registry for one and calls
 * this interface, so adding ABA PayWay or Bakong KHQR later is a new class
 * plus a config line, not a change to CheckoutService.
 */
interface PaymentGateway
{
    /** Machine key used in config and stored on the payment row. */
    public function key(): string;

    /** Shown to the customer at checkout. */
    public function label(): string;

    /**
     * Begins payment for an order.
     *
     * Returns whatever the client needs next: a redirect URL, a QR payload,
     * or nothing at all for a gateway settled offline.
     *
     * @return array{payment: Payment, redirect_url?: string, qr?: string}
     */
    public function initiate(Order $order): array;

    /**
     * Handles an asynchronous callback from the provider.
     *
     * Implementations MUST verify the signature before trusting anything in
     * the request — an unverified webhook is an open door to marking any
     * order paid.
     */
    public function handleWebhook(Request $request): ?Payment;

    /** Whether this gateway is configured well enough to be offered. */
    public function isAvailable(): bool;
}
