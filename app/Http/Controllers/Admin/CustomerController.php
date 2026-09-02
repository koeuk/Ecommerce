<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        // Lifetime value counts paid money only — an unpaid basket is not revenue.
        $paidOrders = fn ($q) => $q->where('payment_status', PaymentStatus::Paid);

        $customers = QueryBuilder::for(User::role(Role::Customer->value))
            ->withCount('orders')
            ->withSum(['orders as lifetime_value' => $paidOrders], 'grand_total')
            ->withMax('orders as last_order_at', 'placed_at')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(
                    fn ($b) => $b->where('name', 'like', "%{$v}%")
                        ->orWhere('email', 'like', "%{$v}%")
                        ->orWhere('phone', 'like', "%{$v}%")
                )),
                AllowedFilter::callback('status', fn ($q, $v) => $q->where('is_active', $v === 'active')),
                AllowedFilter::callback('from', fn ($q, $v) => $q->whereDate('created_at', '>=', $v)),
                AllowedFilter::callback('to', fn ($q, $v) => $q->whereDate('created_at', '<=', $v)),
            ])
            ->allowedSorts(...['name', 'created_at', 'orders_count', 'lifetime_value', 'last_order_at'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'orders_count' => $customer->orders_count,
                'lifetime_value' => round((float) ($customer->lifetime_value ?? 0), 2),
                'last_order_at' => $customer->last_order_at
                    ? Carbon::parse($customer->last_order_at)->format('d M Y')
                    : null,
                'joined_at' => $customer->created_at->format('d M Y'),
            ]);

        $base = fn () => User::role(Role::Customer->value);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->input('filter', []),
            'summary' => [
                'total' => $base()->count(),
                'new_this_month' => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
                'active' => $base()->where('is_active', true)->count(),
                'with_orders' => $base()->has('orders')->count(),
            ],
        ]);
    }

    public function show(User $customer): Response
    {
        // Admin accounts share the users table; only role "customer" belongs here.
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        $customer->load(['addresses' => fn ($q) => $q
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')]);

        $paid = $customer->orders()->where('payment_status', PaymentStatus::Paid);
        $paidCount = (clone $paid)->count();
        $lifetime = round((float) (clone $paid)->sum('grand_total'), 2);
        $lastOrderAt = $customer->orders()->max('placed_at');

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified' => $customer->email_verified_at !== null,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'joined_at' => $customer->created_at->format('d M Y H:i'),
                'last_login_at' => $customer->last_login_at?->format('d M Y H:i'),
                'addresses' => $customer->addresses->map(fn (UserAddress $address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'receiver_name' => $address->receiver_name,
                    'phone' => $address->phone,
                    'full_address' => $address->full_address,
                    'country_code' => $address->country_code,
                    'is_default_shipping' => $address->is_default_shipping,
                    'is_default_billing' => $address->is_default_billing,
                ]),
            ],
            'stats' => [
                'orders_count' => $customer->orders()->count(),
                'lifetime_value' => $lifetime,
                'average_order' => $paidCount > 0 ? round($lifetime / $paidCount, 2) : 0,
                'last_order_at' => $lastOrderAt ? Carbon::parse($lastOrderAt)->format('d M Y') : null,
            ],
            'orders' => $customer->orders()
                ->withCount('items')
                ->latest('placed_at')
                ->limit(10)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'items_count' => $order->items_count,
                    'grand_total' => (float) $order->grand_total,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'payment_status' => $order->payment_status->value,
                    'placed_at' => $order->placed_at?->format('d M Y H:i'),
                ]),
        ]);
    }

    public function toggleActive(User $customer): RedirectResponse
    {
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        // Read-modify-write: two admins toggling at once would otherwise both
        // read the same value and one flip would be silently lost.
        DB::transaction(function () use ($customer) {
            $locked = User::lockForUpdate()->findOrFail($customer->id);

            $locked->update(['is_active' => ! $locked->is_active]);

            $customer->setAttribute('is_active', $locked->is_active);
        });

        return back()->with(
            'success',
            $customer->is_active ? 'Customer account activated.' : 'Customer account deactivated.'
        );
    }
}
