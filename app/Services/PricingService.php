<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * The single authority on money. Nothing that reaches a customer's browser
 * is ever trusted to come back as a total — every figure here is recomputed
 * from the cart and the catalog.
 *
 * Tax is calculated but currently resolves to zero: the shop does not charge
 * VAT, so `tax_rates` sits unused. The hook stays so switching it on later
 * is a data change, not a code change.
 */
class PricingService
{
    /**
     * @param  Collection<int, array>  $lines  cart summary lines
     */
    public function quote(
        Collection $lines,
        ?string $province = null,
        ?int $shippingMethodId = null,
        ?string $couponCode = null,
    ): array {
        $subtotal = round((float) $lines->sum('subtotal'), 2);
        $weight = (float) $lines->sum(fn (array $l) => ($l['weight'] ?? 0) * $l['quantity']);

        $zone = ShippingZone::forProvince($province);
        $methods = $this->methodsFor($zone, $subtotal, $weight);
        $method = $this->resolveMethod($methods, $shippingMethodId);

        $shipping = $method['cost'] ?? 0.0;

        [$discount, $coupon, $couponError] = $this->discount($couponCode, $subtotal);

        // Discount never exceeds the goods value.
        $discount = min($discount, $subtotal);

        $tax = $this->tax($subtotal - $discount);

        return [
            'subtotal' => $subtotal,
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'shipping_fee' => round($shipping, 2),
            'grand_total' => round($subtotal - $discount + $tax + $shipping, 2),
            'currency' => 'USD',

            'coupon' => $coupon ? ['id' => $coupon->id, 'code' => $coupon->code] : null,
            'coupon_error' => $couponError,

            'shipping_zone' => $zone?->name,
            'shipping_method_id' => $method['id'] ?? null,
            'shipping_methods' => $methods,
        ];
    }

    /** Options available for a zone, each with its cost for this cart. */
    private function methodsFor(?ShippingZone $zone, float $subtotal, float $weight): array
    {
        if (! $zone) {
            return [];
        }

        return $zone->methods()->active()->orderBy('sort_order')->get()
            ->map(fn (ShippingMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'description' => $method->description,
                'cost' => $method->calculate($subtotal, $weight),
                'min_days' => $method->min_days,
                'max_days' => $method->max_days,
            ])
            ->all();
    }

    /** The chosen method, or the cheapest when none was picked. */
    private function resolveMethod(array $methods, ?int $requestedId): ?array
    {
        if ($methods === []) {
            return null;
        }

        if ($requestedId !== null) {
            $chosen = collect($methods)->firstWhere('id', $requestedId);

            if ($chosen) {
                return $chosen;
            }
        }

        return collect($methods)->sortBy('cost')->first();
    }

    /** @return array{0: float, 1: ?Coupon, 2: ?string} */
    private function discount(?string $code, float $subtotal): array
    {
        if (blank($code)) {
            return [0.0, null, null];
        }

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return [0.0, null, __('That coupon code is not valid.')];
        }

        if (! $coupon->is_active || $coupon->is_expired || $coupon->is_exhausted) {
            return [0.0, null, __('That coupon is no longer available.')];
        }

        $discount = $coupon->discountFor($subtotal);

        if ($discount <= 0) {
            return [0.0, null, __('This order does not meet the coupon minimum.')];
        }

        return [$discount, $coupon, null];
    }

    /**
     * The shop does not charge VAT, so this is zero. Kept as a seam so the
     * `tax_rates` table can be switched on without touching checkout.
     */
    private function tax(float $taxableTotal): float
    {
        return 0.0;
    }
}
