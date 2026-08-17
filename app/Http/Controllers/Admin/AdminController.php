<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->stats(),
            'salesChart' => $this->salesChart(),
            'ordersByStatus' => $this->ordersByStatus(),
            'lowStock' => $this->lowStock(),
            'recentOrders' => $this->recentOrders(),
            'topProducts' => $this->topProducts(),
        ]);
    }

    private function stats(): array
    {
        $paid = Order::where('payment_status', PaymentStatus::Paid);

        return [
            'revenue_today' => round((float) (clone $paid)->whereDate('placed_at', today())->sum('grand_total'), 2),
            'revenue_month' => round((float) (clone $paid)->whereMonth('placed_at', now()->month)
                ->whereYear('placed_at', now()->year)->sum('grand_total'), 2),
            'orders_total' => Order::count(),
            'orders_pending' => Order::where('status', OrderStatus::Pending)->count(),
            'products_total' => Product::count(),
            'products_published' => Product::where('status', ProductStatus::Published)->count(),
            'customers_total' => User::role('customer')->count(),
            'low_stock_count' => ProductVariant::lowStock()->count(),
            'categories_total' => Category::count(),
            'brands_total' => Brand::count(),
        ];
    }

    /** Revenue for the last 14 days, zero-filled so the chart has no gaps. */
    private function salesChart(): array
    {
        $rows = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('placed_at', [now()->subDays(13)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(placed_at) as day, SUM(grand_total) as total, COUNT(*) as orders')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(13, 0))
            ->map(function (int $daysAgo) use ($rows) {
                $date = now()->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'label' => now()->subDays($daysAgo)->format('d M'),
                    'total' => round((float) ($rows[$date] ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function ordersByStatus(): array
    {
        $counts = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    private function lowStock(): array
    {
        return ProductVariant::lowStock()
            ->with('product:id,title,slug')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_title' => $variant->product?->getTranslation('title', 'en'),
                'sku' => $variant->sku,
                'label' => $variant->label,
                'stock_quantity' => $variant->stock_quantity,
                'threshold' => $variant->low_stock_threshold,
            ])
            ->all();
    }

    private function recentOrders(): array
    {
        return Order::latest('placed_at')
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'grand_total' => (float) $order->grand_total,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'placed_at' => $order->placed_at?->diffForHumans(),
            ])
            ->all();
    }

    private function topProducts(): array
    {
        return Product::query()
            ->published()
            ->with('primaryImage')
            ->orderByDesc('views_count')
            ->limit(5)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->getTranslation('title', 'en'),
                'price' => (float) $product->price,
                'stock_quantity' => $product->stock_quantity,
                'views_count' => $product->views_count,
                'image_url' => $product->primaryImage?->url,
            ])
            ->all();
    }
}
