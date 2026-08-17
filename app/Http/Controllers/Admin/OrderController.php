<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): Response
    {
        $orders = QueryBuilder::for(Order::class)
            ->with('user:id,name')
            ->withCount('items')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(
                    fn ($b) => $b->where('order_number', 'like', "%{$v}%")
                        ->orWhere('customer_name', 'like', "%{$v}%")
                        ->orWhere('customer_phone', 'like', "%{$v}%")
                        ->orWhere('customer_email', 'like', "%{$v}%")
                )),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('payment_status'),
                AllowedFilter::callback('from', fn ($q, $v) => $q->whereDate('placed_at', '>=', $v)),
                AllowedFilter::callback('to', fn ($q, $v) => $q->whereDate('placed_at', '<=', $v)),
            ])
            ->allowedSorts(...['order_number', 'grand_total', 'placed_at', 'created_at'])
            ->defaultSort('-placed_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'is_guest' => $order->is_guest_order,
                'items_count' => $order->items_count,
                'grand_total' => (float) $order->grand_total,
                'currency' => $order->currency,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'placed_at' => $order->placed_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->input('filter', []),
            'options' => [
                'statuses' => $this->statusOptions(),
                'payment_statuses' => array_map(
                    fn (PaymentStatus $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))],
                    PaymentStatus::cases()
                ),
            ],
            'summary' => [
                'pending' => Order::where('status', OrderStatus::Pending)->count(),
                'unpaid' => Order::where('payment_status', PaymentStatus::Unpaid)->count(),
                'today_count' => Order::whereDate('placed_at', today())->count(),
                'today_revenue' => round((float) Order::whereDate('placed_at', today())
                    ->where('payment_status', PaymentStatus::Paid)->sum('grand_total'), 2),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'items',
            'user:id,name,email',
            'shippingMethod',
            'coupon:id,code',
            'payments',
            'statusHistories.author:id,name',
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,

                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'account' => $order->user ? ['id' => $order->user->id, 'name' => $order->user->name] : null,

                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,

                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'tax_total' => (float) $order->tax_total,
                'shipping_fee' => (float) $order->shipping_fee,
                'grand_total' => (float) $order->grand_total,
                'currency' => $order->currency,

                'coupon' => $order->coupon?->code,
                'shipping_method' => $order->shippingMethod?->getTranslation('name', 'en'),
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,

                'customer_note' => $order->customer_note,
                'admin_note' => $order->admin_note,

                'placed_at' => $order->placed_at?->format('d M Y H:i'),
                'paid_at' => $order->paid_at?->format('d M Y H:i'),
                'shipped_at' => $order->shipped_at?->format('d M Y H:i'),
                'delivered_at' => $order->delivered_at?->format('d M Y H:i'),
                'cancelled_at' => $order->cancelled_at?->format('d M Y H:i'),

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

                'timeline' => $order->statusHistories->map(fn ($h) => [
                    'id' => $h->id,
                    'from' => $h->from_status?->value,
                    'to' => $h->to_status->value,
                    'note' => $h->note,
                    'author' => $h->author?->name,
                    'at' => $h->created_at->format('d M Y H:i'),
                ]),

                // What the enum actually permits from here — the UI renders
                // exactly these buttons and nothing else.
                'allowed_transitions' => array_map(
                    fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    $order->status->allowedTransitions()
                ),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(OrderStatus::values())],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->orders->transition(
            $order,
            OrderStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('success', 'Order status updated.');
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $this->orders->markPaid($order, $request->user());

        return back()->with('success', 'Order marked as paid.');
    }

    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->update(['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('success', 'Note saved.');
    }

    private function statusOptions(): array
    {
        return array_map(
            fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            OrderStatus::cases()
        );
    }
}
