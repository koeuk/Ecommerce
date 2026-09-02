<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cart identity, decided here once so every endpoint agrees:
 *
 *   Authenticated  → keyed on `user_id`.
 *   Guest          → keyed on `cart_items.session_id`, holding a token the
 *                    server mints and the client echoes back in the
 *                    `X-Cart-Token` header. The API has no session cookie to
 *                    lean on, so the token *is* the guest's identity.
 *
 * On login the guest rows are folded into the user's cart and the guest rows
 * dropped, so signing in never loses or duplicates a cart.
 */
class CartService
{
    public function newToken(): string
    {
        return (string) Str::uuid();
    }

    /** @return Collection<int, CartItem> */
    public function items(?User $user, ?string $token): Collection
    {
        return $this->scope($user, $token)
            ->with(['product.primaryImage', 'variant.product'])
            ->get();
    }

    public function add(?User $user, ?string $token, int $variantId, int $quantity): CartItem
    {
        $variant = ProductVariant::with('product')->findOrFail($variantId);

        $this->assertPurchasable($variant);

        return DB::transaction(function () use ($user, $token, $variant, $quantity) {
            $existing = $this->scope($user, $token)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            // Adding a line already in the cart tops it up rather than
            // creating a duplicate — the table is unique on it anyway.
            $target = ($existing?->quantity ?? 0) + $quantity;

            $this->assertStock($variant, $target);

            if ($existing) {
                $existing->update(['quantity' => $target]);

                return $existing;
            }

            return CartItem::create([
                'user_id' => $user?->id,
                'session_id' => $user ? null : $token,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'price_at_add' => $variant->price,
            ]);
        });
    }

    public function update(?User $user, ?string $token, int $itemId, int $quantity): ?CartItem
    {
        return DB::transaction(function () use ($user, $token, $itemId, $quantity) {
            $item = $this->findItem($user, $token, $itemId);

            if ($quantity <= 0) {
                $item->delete();

                return null;
            }

            // Lock the variant so the stock read and the write cannot be
            // separated by somebody else's checkout.
            $variant = ProductVariant::lockForUpdate()->find($item->product_variant_id);

            $this->assertStock($variant, $quantity);

            $item->update(['quantity' => $quantity]);

            return $item;
        });
    }

    public function remove(?User $user, ?string $token, int $itemId): void
    {
        $this->findItem($user, $token, $itemId)->delete();
    }

    public function clear(?User $user, ?string $token): void
    {
        $this->scope($user, $token)->delete();
    }

    /**
     * Folds a guest cart into the user's own, then drops the guest rows.
     *
     * Where both carts hold the same variant the quantities are summed and
     * clamped to what stock allows, so merging can never create an
     * unfulfillable line.
     */
    public function merge(User $user, ?string $token): void
    {
        if (blank($token)) {
            return;
        }

        DB::transaction(function () use ($user, $token) {
            $guestItems = CartItem::forSession($token)->with('variant')->get();

            foreach ($guestItems as $guestItem) {
                // A line that sold out while the guest was browsing is dropped
                // rather than merged — carrying it over would put an
                // unbuyable row in the cart that checkout then refuses.
                if ($this->clampToStock($guestItem->variant, $guestItem->quantity) === 0) {
                    $guestItem->delete();

                    continue;
                }

                $mine = CartItem::forUser($user->id)
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($mine) {
                    $combined = $mine->quantity + $guestItem->quantity;

                    $mine->update([
                        'quantity' => $this->clampToStock($guestItem->variant, $combined),
                    ]);

                    $guestItem->delete();

                    continue;
                }

                // No conflict — hand the row over rather than copying it.
                $guestItem->update([
                    'user_id' => $user->id,
                    'session_id' => null,
                    'quantity' => $this->clampToStock($guestItem->variant, $guestItem->quantity),
                ]);
            }
        });
    }

    /**
     * Totals, computed server-side. A cart can go stale between reads, so
     * each line reports whether it is still purchasable at the price it was
     * added at.
     */
    public function summary(?User $user, ?string $token): array
    {
        $items = $this->items($user, $token);

        $lines = $items->map(fn (CartItem $item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'title' => $item->product?->title,
            'slug' => $item->product?->slug,
            'variant_label' => $item->variant?->label,
            'sku' => $item->variant?->sku,
            'image_url' => $item->product?->primary_image_url,
            'quantity' => $item->quantity,
            'price_at_add' => (float) $item->price_at_add,
            'unit_price' => $item->current_price,
            'subtotal' => $item->subtotal,
            'price_changed' => $item->price_changed,
            'in_stock' => (bool) $item->variant?->canFulfil($item->quantity),
            'available_stock' => $item->variant?->stock_quantity,
        ])->values();

        return [
            'items' => $lines,
            'item_count' => (int) $items->sum('quantity'),
            'line_count' => $items->count(),
            'subtotal' => round((float) $items->sum(fn (CartItem $i) => $i->subtotal), 2),
            'has_issues' => $lines->contains(fn (array $l) => $l['price_changed'] || ! $l['in_stock']),
        ];
    }

    // Internals

    private function scope(?User $user, ?string $token)
    {
        if ($user) {
            return CartItem::forUser($user->id);
        }

        // A guest with no token has no cart — never fall through to every
        // row with a null session_id.
        return CartItem::forSession((string) $token)->whereNotNull('session_id');
    }

    private function findItem(?User $user, ?string $token, int $itemId): CartItem
    {
        return $this->scope($user, $token)->with('variant')->findOrFail($itemId);
    }

    private function assertPurchasable(ProductVariant $variant): void
    {
        if (! $variant->is_active || $variant->product?->status !== ProductStatus::Published) {
            throw ValidationException::withMessages([
                'variant_id' => __('This item is not available for purchase.'),
            ]);
        }
    }

    private function assertStock(?ProductVariant $variant, int $quantity): void
    {
        if (! $variant?->canFulfil($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => __('Only :count left in stock.', ['count' => $variant?->stock_quantity ?? 0]),
            ]);
        }
    }

    /**
     * Caps a quantity at what stock allows. Returns 0 when nothing can be
     * fulfilled — a `max(1, …)` floor here would resurrect a sold-out line
     * as a phantom quantity of one.
     */
    private function clampToStock(?ProductVariant $variant, int $quantity): int
    {
        if (! $variant) {
            return 0;
        }

        if ($variant->allow_backorder) {
            return $quantity;
        }

        return max(0, min($quantity, $variant->stock_quantity));
    }
}
