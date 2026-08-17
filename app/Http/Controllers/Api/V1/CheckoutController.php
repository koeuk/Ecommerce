<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckoutRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
        private readonly PricingService $pricing,
    ) {}

    /**
     * POST /api/v1/checkout/quote
     *
     * Totals for the current cart against an address, without committing.
     * This is what the checkout screen renders shipping options from.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province' => ['nullable', 'string', 'max:120'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
        ]);

        $summary = $this->cart->summary($this->user($request), $this->token($request));

        return response()->json([
            'data' => $this->pricing->quote(
                collect($summary['items']),
                $data['province'] ?? null,
                $data['shipping_method_id'] ?? null,
                $data['coupon_code'] ?? null,
            ) + ['cart' => $summary],
        ]);
    }

    /** POST /api/v1/checkout — places the order. */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkout->place(
            $this->user($request),
            $this->token($request),
            $request->validated(),
        );

        return response()->json(['data' => $this->serialise($order)], 201);
    }

    /**
     * GET /api/v1/orders/{number}
     *
     * Guests track by order number plus the phone or email on the order —
     * the number alone would let anyone walk the sequence.
     */
    public function track(Request $request, string $number): JsonResponse
    {
        $order = Order::with('items')->where('order_number', $number)->firstOrFail();

        $user = $this->user($request);

        if ($user && $order->user_id === $user->id) {
            return response()->json(['data' => $this->serialise($order)]);
        }

        $contact = (string) $request->query('contact');

        if ($contact === ''
            || (! hash_equals((string) $order->customer_phone, $contact)
                && ! hash_equals((string) $order->customer_email, $contact))
        ) {
            return response()->json([
                'message' => __('Order not found, or the contact details do not match.'),
            ], 404);
        }

        return response()->json(['data' => $this->serialise($order)]);
    }

    private function serialise(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,

            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,

            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'tax_total' => (float) $order->tax_total,
            'shipping_fee' => (float) $order->shipping_fee,
            'grand_total' => (float) $order->grand_total,
            'currency' => $order->currency,

            'shipping_address' => $order->shipping_address,
            'customer_note' => $order->customer_note,
            'placed_at' => $order->placed_at?->toIso8601String(),

            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'sku' => $item->sku,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
            ]),
        ];
    }

    private function user(Request $request): ?User
    {
        return auth('sanctum')->user();
    }

    private function token(Request $request): ?string
    {
        return $this->user($request) ? null : $request->header('X-Cart-Token');
    }
}
