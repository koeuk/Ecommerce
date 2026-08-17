<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Every stock change goes through here so `inventory_movements` stays a
 * complete audit trail — the running total should always be reconstructible
 * from it.
 */
class InventoryService
{
    /** Decrements stock for each order line. Call inside the order transaction. */
    public function decrementForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_variant_id) {
                continue;
            }

            $this->move(
                $item->product_variant_id,
                -$item->quantity,
                'out',
                "Order {$order->order_number}",
                $order->created_by,
            );
        }
    }

    /**
     * Returns stock when an order is cancelled or refunded. Guarded so a
     * double-cancel cannot inflate inventory.
     */
    public function restockForOrder(Order $order): void
    {
        if ($order->stock_released_at !== null) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $this->move(
                    $item->product_variant_id,
                    $item->quantity,
                    'return',
                    "Restock from {$order->order_number}",
                    $order->updated_by,
                );
            }

            $order->forceFill(['stock_released_at' => now()])->save();
        });
    }

    /**
     * Applies a delta and records the movement.
     *
     * @param  int  $delta  negative to remove stock, positive to add
     */
    public function move(int $variantId, int $delta, string $type, string $reason, ?int $userId = null): void
    {
        $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);

        $after = max(0, $variant->stock_quantity + $delta);

        $variant->update(['stock_quantity' => $after]);

        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $delta,
            'stock_after' => $after,
            'reason' => $reason,
            'created_by' => $userId,
        ]);

        $variant->product?->syncStockFromVariants();
    }
}
