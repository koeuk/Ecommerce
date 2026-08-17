<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable, sequential per day: ORD-20260817-0001
 *
 * The counter restarts each day, so the number stays short enough to read
 * over the phone — which is how most COD orders get chased up.
 */
class OrderNumberGenerator
{
    public function generate(): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$date}-";

        // Locking the day's highest row keeps two concurrent checkouts from
        // claiming the same number.
        return DB::transaction(function () use ($prefix) {
            $last = Order::withTrashed()
                ->where('order_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = $last
                ? ((int) substr($last, -4)) + 1
                : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
