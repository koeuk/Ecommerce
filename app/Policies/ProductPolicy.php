<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

/**
 * Route middleware already gates on the bare permission. A policy adds the
 * per-record rules that middleware cannot express — here, protecting a
 * product that order history still points at.
 *
 * Super admin bypasses all of this via Gate::before in AppServiceProvider.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view product');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('view product');
    }

    public function create(User $user): bool
    {
        return $user->can('create product');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('update product');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('delete product');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('update product');
    }

    /**
     * Hard-deleting a product that order lines reference would break the
     * audit trail, so it is refused regardless of permission.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        if (OrderItem::where('product_id', $product->id)->exists()) {
            return false;
        }

        return $user->can('delete product');
    }
}
