<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

/**
 * Coupons are pure admin data — there is no customer-facing view here, only
 * redemption, which the checkout flow validates on its own.
 *
 * Super admin bypasses all of this via Gate::before in AppServiceProvider.
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view coupon');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->can('view coupon');
    }

    public function create(User $user): bool
    {
        return $user->can('create coupon');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can('update coupon');
    }

    /**
     * A redeemed coupon is referenced by order history. Deactivate it
     * instead of deleting it, so past discounts stay explainable.
     */
    public function delete(User $user, Coupon $coupon): bool
    {
        if ($coupon->usages()->exists()) {
            return false;
        }

        return $user->can('delete coupon');
    }
}
