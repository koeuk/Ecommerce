<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            // Guests may checkout without an account, but an email is how
            // they get their confirmation and track the order later.
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],

            'shipping_address' => ['required', 'array'],
            'shipping_address.receiver_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['required', 'string', 'max:120'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_address.country_code' => ['nullable', 'string', 'size:2'],

            'billing_address' => ['nullable', 'array'],

            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'customer_note' => ['nullable', 'string', 'max:2000'],

            // COD is the only method today; the field exists so adding a
            // gateway in Phase 7 does not change the contract.
            'payment_method' => ['nullable', 'in:cod'],
        ];
    }

    public function attributes(): array
    {
        return [
            'shipping_address.state' => 'province',
            'shipping_address.address_line1' => 'address',
        ];
    }
}
