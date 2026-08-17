<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Order fulfilment. `OrderStatus` owns which transitions are legal and which
 * states release stock, so this class defers to the enum rather than
 * restating those rules.
 */
class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function transition(Order $order, OrderStatus $target, ?User $actor = null, ?string $note = null): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('An order cannot go from :from to :to.', [
                    'from' => $from->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($order, $from, $target, $actor, $note) {
            $order->status = $target;

            // Timestamps the admin and the customer both read off.
            match ($target) {
                OrderStatus::Shipped => $order->shipped_at = now(),
                OrderStatus::Delivered => $order->delivered_at = now(),
                OrderStatus::Cancelled => $order->cancelled_at = now(),
                default => null,
            };

            if ($target === OrderStatus::Shipped) {
                $order->fulfillment_status = FulfillmentStatus::Fulfilled;
            }

            if ($target === OrderStatus::Refunded) {
                $order->payment_status = PaymentStatus::Refunded;
            }

            $order->updated_by = $actor?->id;
            $order->save();

            // Cancelled and refunded put the goods back on the shelf. The
            // service guards against restocking the same order twice.
            if ($target->releasesStock()) {
                $this->inventory->restockForOrder($order->load('items'));
            }

            $order->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $target->value,
                'note' => $note,
                'created_by' => $actor?->id,
            ]);

            $order = $order->fresh();

            $this->notify($order, $target);

            return $order;
        });
    }

    /** Only the stages a customer cares about get an email. */
    private function notify(Order $order, OrderStatus $status): void
    {
        $stage = match ($status) {
            OrderStatus::Shipped => 'shipped',
            OrderStatus::Delivered => 'delivered',
            OrderStatus::Cancelled => 'cancelled',
            default => null,
        };

        if ($stage === null || blank($order->customer_email)) {
            return;
        }

        Mail::to($order->customer_email)->send(new OrderMail($order->load('items'), $stage));
    }

    /**
     * COD is collected on delivery, so payment is marked by hand rather than
     * by a gateway callback.
     */
    public function markPaid(Order $order, ?User $actor = null): Order
    {
        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'updated_by' => $actor?->id,
        ])->save();

        $order->statusHistories()->create([
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'note' => 'Payment received',
            'created_by' => $actor?->id,
        ]);

        return $order->fresh();
    }
}
