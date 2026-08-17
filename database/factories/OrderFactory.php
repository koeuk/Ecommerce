<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Totals are kept internally consistent:
 *   grand_total = subtotal - discount_total + tax_total + shipping_fee
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 2000);
        $shipping = fake()->randomElement([0, 1.5, 3.0]);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'session_id' => null,

            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('0## ### ###'),

            'subtotal' => $subtotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_fee' => $shipping,
            'grand_total' => round($subtotal + $shipping, 2),

            'currency' => 'USD',
            'exchange_rate' => 1,

            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,

            'coupon_id' => null,
            'shipping_method_id' => null,

            // Snapshots, matching UserAddress::toSnapshot().
            'shipping_address' => $this->addressSnapshot(),
            'billing_address' => null,

            'customer_note' => null,
            'admin_note' => null,

            'placed_at' => now(),
        ];
    }

    private function addressSnapshot(): array
    {
        return [
            'receiver_name' => fake()->name(),
            'phone' => fake()->numerify('0## ### ###'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => 'Phnom Penh',
            'state' => 'Phnom Penh',
            'postal_code' => fake()->numerify('#####'),
            'country_code' => 'KH',
        ];
    }

    /** No account — checkout completed as a guest. */
    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'session_id' => fake()->uuid(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => PaymentStatus::Paid,
            'status' => OrderStatus::Confirmed,
            'paid_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->paid()->state(fn () => [
            'status' => OrderStatus::Shipped,
            'fulfillment_status' => FulfillmentStatus::Fulfilled,
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->shipped()->state(fn () => [
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /** Placed on a specific day — for dashboard revenue assertions. */
    public function placedAt(\DateTimeInterface $date): static
    {
        return $this->state(fn () => [
            'placed_at' => $date,
            'created_at' => $date,
        ]);
    }

    public function withTotals(float $subtotal, float $shipping = 0, float $discount = 0, float $tax = 0): static
    {
        return $this->state(fn () => [
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => round($subtotal - $discount + $tax + $shipping, 2),
        ]);
    }
}
