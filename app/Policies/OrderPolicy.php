<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * Orders carry both an admin view and a customer view, so this policy is
 * where the two meet: a customer may read their own order and nothing else.
 *
 * Super admin bypasses all of this via Gate::before in AppServiceProvider.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view order');
    }

    /** Admins with the permission, or the customer who placed it. */
    public function view(User $user, Order $order): bool
    {
        return $user->can('view order') || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create order');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('update order');
    }

    /**
     * A status change must be legal for the current status — OrderStatus
     * owns that rule, so the policy defers to it rather than restating it.
     */
    public function transitionTo(User $user, Order $order, OrderStatus $target): bool
    {
        return $user->can('update order') && $order->canTransitionTo($target);
    }

    /**
     * Cancelling is a status change, not a delete.
     *
     * Unlike the other transitions, the customer who placed the order may do
     * this to their own — they hold no `update order` permission, so the
     * ownership check is what authorises them. The legality of the move is
     * still `OrderStatus`'s call, which is what stops a shipped order being
     * called back.
     */
    public function cancel(User $user, Order $order): bool
    {
        $mayAct = $user->can('update order') || $order->user_id === $user->id;

        return $mayAct && $order->canTransitionTo(OrderStatus::Cancelled);
    }

    /**
     * A paid order is a financial record. Deleting it would leave payments
     * and refunds pointing at nothing, so it is refused outright.
     */
    public function delete(User $user, Order $order): bool
    {
        if ($order->payments()->exists() || $order->paid_at !== null) {
            return false;
        }

        return $user->can('delete order');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false; // Order history is never hard-deleted.
    }
}
