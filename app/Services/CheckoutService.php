<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order: validate stock → create the order →
 * decrement stock, all inside one transaction so a failure part-way leaves
 * neither a half order nor a wrong stock level.
 *
 * Cash on Delivery only. The order is created `pending` / `unpaid`; payment
 * status moves when the courier hands the cash over.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly OrderNumberGenerator $numbers,
    ) {}

    public function place(?User $user, ?string $cartToken, array $data): Order
    {
        $summary = $this->cart->summary($user, $cartToken);
        $lines = collect($summary['items']);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => __('Your cart is empty.'),
            ]);
        }

        $address = $data['shipping_address'];

        $quote = $this->pricing->quote(
            $lines,
            $address['state'] ?? null,
            $data['shipping_method_id'] ?? null,
            $data['coupon_code'] ?? null,
        );

        // A coupon that failed to apply must not silently drop off the order.
        if (($data['coupon_code'] ?? null) && $quote['coupon_error']) {
            throw ValidationException::withMessages(['coupon_code' => $quote['coupon_error']]);
        }

        return DB::transaction(function () use ($user, $cartToken, $data, $address, $lines, $quote) {
            // Re-check stock under lock — the cart may have gone stale since
            // it was last read.
            $this->assertStillPurchasable($lines);

            $order = Order::create([
                'order_number' => $this->numbers->generate(),
                'user_id' => $user?->id,
                'session_id' => $user ? null : $cartToken,

                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? $user?->email,
                'customer_phone' => $data['customer_phone'],

                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'tax_total' => $quote['tax_total'],
                'shipping_fee' => $quote['shipping_fee'],
                'grand_total' => $quote['grand_total'],

                'currency' => $quote['currency'],
                'exchange_rate' => 1,

                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,

                'coupon_id' => $quote['coupon']['id'] ?? null,
                'shipping_method_id' => $quote['shipping_method_id'],

                'shipping_address' => $address,
                'billing_address' => $data['billing_address'] ?? $address,

                'customer_note' => $data['customer_note'] ?? null,
                'placed_at' => now(),
                'created_by' => $user?->id,
            ]);

            $this->createItems($order, $lines);

            $order->load('items');

            $this->inventory->decrementForOrder($order);

            $this->recordCouponUsage($order, $user);

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => OrderStatus::Pending->value,
                'note' => 'Order placed',
                'created_by' => $user?->id,
            ]);

            // The cart has become the order.
            $this->cart->clear($user, $cartToken);

            return $order->fresh(['items']);
        });
    }

    /**
     * Snapshots each line onto the order. Product name, SKU and price are
     * copied rather than referenced, so later catalog edits never rewrite
     * what somebody was charged.
     */
    private function createItems(Order $order, $lines): void
    {
        foreach ($lines as $line) {
            $variant = ProductVariant::with('product')->find($line['variant_id']);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'product_variant_id' => $line['variant_id'],
                'product_name' => $variant?->product?->getTranslation('title', 'en') ?? '',
                'sku' => $line['sku'] ?? '',
                'variant_label' => $line['variant_label'] ?? null,
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'subtotal' => $line['subtotal'],
            ]);
        }
    }

    private function assertStillPurchasable($lines): void
    {
        foreach ($lines as $line) {
            $variant = ProductVariant::lockForUpdate()->find($line['variant_id']);

            if (! $variant?->canFulfil($line['quantity'])) {
                throw ValidationException::withMessages([
                    'cart' => __(':item is no longer available in that quantity.', [
                        'item' => $line['sku'] ?? 'An item',
                    ]),
                ]);
            }
        }
    }

    private function recordCouponUsage(Order $order, ?User $user): void
    {
        if (! $order->coupon_id) {
            return;
        }

        $order->coupon?->usages()->create([
            'user_id' => $user?->id,
            'order_id' => $order->id,
            'discount_amount' => $order->discount_total,
        ]);

        Coupon::whereKey($order->coupon_id)->increment('used_count');
    }
}
