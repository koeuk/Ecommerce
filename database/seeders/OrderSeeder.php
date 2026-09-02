<?php

namespace Database\Seeders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Two months of order history so the dashboard, orders list and revenue
 * chart have data to show. Appends only — safe to re-run.
 */
class OrderSeeder extends Seeder
{
    private const LIFECYCLE = [
        OrderStatus::Pending,
        OrderStatus::Confirmed,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    public function run(): void
    {
        $variants = ProductVariant::with('product.images')
            ->where('is_active', true)
            ->get();

        if ($variants->isEmpty()) {
            $this->command?->warn('No active variants — run ProductSeeder first.');

            return;
        }

        $customers = User::factory()
            ->count(12)
            ->create()
            ->each(fn (User $u) => $u->assignRole(Role::Customer->value));

        $sequence = 0;

        foreach ($this->placementDates() as $placedAt) {
            $this->makeOrder($variants, $customers, $placedAt, ++$sequence);
        }

        $this->command?->info("Seeded {$sequence} orders across the last 60 days.");
    }

    /**
     * One to a few orders per day for 60 days, denser in the last two weeks.
     * Today always gets a handful so "Revenue today" is non-zero.
     */
    private function placementDates(): array
    {
        $dates = [];

        foreach (range(59, 0) as $daysAgo) {
            $count = $daysAgo <= 13 ? random_int(2, 5) : random_int(1, 3);

            if ($daysAgo === 0) {
                $count = max($count, 4);
            }

            foreach (range(1, $count) as $i) {
                $date = now()->subDays($daysAgo)
                    ->setTime(random_int(8, 21), random_int(0, 59));

                if ($date->isFuture()) {
                    $date = now()->subMinutes(random_int(10, 300));
                }

                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function makeOrder($variants, $customers, Carbon $placedAt, int $sequence): void
    {
        $lines = $variants
            ->random(random_int(1, min(4, $variants->count())))
            ->map(fn (ProductVariant $v) => [
                'variant' => $v,
                'quantity' => random_int(1, 3),
            ]);

        $subtotal = round($lines->sum(
            fn (array $l) => $l['quantity'] * (float) $l['variant']->price
        ), 2);

        $status = $this->pickStatus($placedAt);
        $timeline = $this->timeline($status, $placedAt);
        $customer = random_int(1, 100) <= 80 ? $customers->random() : null;

        $order = Order::factory()
            ->withTotals($subtotal, fake()->randomElement([0, 1.5, 3.0]))
            ->placedAt($placedAt)
            ->create([
                'order_number' => 'ORD-'.$placedAt->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'user_id' => $customer?->id,
                'session_id' => $customer ? null : fake()->uuid(),
                'customer_name' => $customer->name ?? fake()->name(),
                'customer_email' => $customer->email ?? fake()->safeEmail(),
                'status' => $status,
                'payment_status' => $this->paymentStatusFor($status),
                'fulfillment_status' => $this->fulfillmentStatusFor($status),
                'paid_at' => $timeline[OrderStatus::Confirmed->value] ?? null,
                'shipped_at' => $timeline[OrderStatus::Shipped->value] ?? null,
                'delivered_at' => $timeline[OrderStatus::Delivered->value] ?? null,
                'cancelled_at' => $timeline[OrderStatus::Cancelled->value] ?? null,
            ]);

        foreach ($lines as $line) {
            $variant = $line['variant'];
            $product = $variant->product;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'product_name' => $product->getTranslation('title', 'en'),
                'variant_label' => $variant->label,
                'sku' => $variant->sku,
                'image_path' => $product->images->firstWhere('is_primary', true)?->path
                    ?? $product->images->first()?->path,
                'quantity' => $line['quantity'],
                'unit_price' => $variant->price,
                'subtotal' => round($line['quantity'] * (float) $variant->price, 2),
                'warranty_months' => $product->warranty_months,
            ]);
        }

        $this->writeHistory($order, $timeline);
    }

    /** Older orders have mostly completed; recent ones are still in flight. */
    private function pickStatus(Carbon $placedAt): OrderStatus
    {
        $daysAgo = (int) $placedAt->diffInDays(now());
        $roll = random_int(1, 100);

        if ($daysAgo >= 7) {
            return match (true) {
                $roll <= 78 => OrderStatus::Delivered,
                $roll <= 88 => OrderStatus::Cancelled,
                $roll <= 94 => OrderStatus::Shipped,
                default => OrderStatus::Processing,
            };
        }

        if ($daysAgo >= 2) {
            return match (true) {
                $roll <= 35 => OrderStatus::Delivered,
                $roll <= 60 => OrderStatus::Shipped,
                $roll <= 75 => OrderStatus::Processing,
                $roll <= 85 => OrderStatus::Confirmed,
                $roll <= 93 => OrderStatus::Pending,
                default => OrderStatus::Cancelled,
            };
        }

        return match (true) {
            $roll <= 20 => OrderStatus::Pending,
            $roll <= 55 => OrderStatus::Confirmed,
            $roll <= 75 => OrderStatus::Processing,
            $roll <= 90 => OrderStatus::Shipped,
            default => OrderStatus::Cancelled,
        };
    }

    private function paymentStatusFor(OrderStatus $status): PaymentStatus
    {
        return match ($status) {
            OrderStatus::Pending, OrderStatus::Cancelled => PaymentStatus::Unpaid,
            default => PaymentStatus::Paid,
        };
    }

    private function fulfillmentStatusFor(OrderStatus $status): FulfillmentStatus
    {
        return match ($status) {
            OrderStatus::Shipped, OrderStatus::Delivered => FulfillmentStatus::Fulfilled,
            default => FulfillmentStatus::Unfulfilled,
        };
    }

    /**
     * Timestamps for each stage the order passed through, a few hours to a
     * day apart, never in the future.
     *
     * @return array<string, Carbon> status value => reached at
     */
    private function timeline(OrderStatus $final, Carbon $placedAt): array
    {
        $path = $final === OrderStatus::Cancelled
            ? [OrderStatus::Pending, OrderStatus::Cancelled]
            : array_slice(
                self::LIFECYCLE,
                0,
                array_search($final, self::LIFECYCLE, true) + 1
            );

        $times = [];
        $cursor = $placedAt->copy();

        foreach ($path as $i => $status) {
            if ($i > 0) {
                $cursor = $cursor->copy()->addHours(random_int(2, 30));
            }

            if ($cursor->isFuture()) {
                $cursor = now()->subMinutes(random_int(5, 60));
            }

            $times[$status->value] = $cursor->copy();
        }

        return $times;
    }

    private function writeHistory(Order $order, array $timeline): void
    {
        $steps = array_keys($timeline);

        foreach ($steps as $i => $step) {
            if ($i === 0) {
                continue;
            }

            $history = new OrderStatusHistory([
                'order_id' => $order->id,
                'from_status' => $steps[$i - 1],
                'to_status' => $step,
            ]);

            // Timestamps are not mass-assignable; backdate them explicitly.
            $history->created_at = $timeline[$step];
            $history->updated_at = $timeline[$step];
            $history->save();
        }
    }
}
