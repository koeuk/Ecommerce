<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Cash on Delivery — the only gateway in use.
 *
 * Nothing is collected online: a pending payment row is recorded so every
 * order has a payment history, and the admin marks it paid when the courier
 * hands the cash over.
 */
class CodGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return __('Cash on Delivery');
    }

    public function initiate(Order $order): array
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => $this->key(),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => PaymentStatus::Unpaid->value,
        ]);

        // Nothing for the client to do — no redirect, no QR.
        return ['payment' => $payment];
    }

    /** COD has no provider to call back. */
    public function handleWebhook(Request $request): ?Payment
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
