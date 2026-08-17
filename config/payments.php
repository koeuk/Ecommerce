<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    |
    | Each entry implements App\Payments\PaymentGateway. CheckoutService
    | resolves the requested gateway through GatewayRegistry and calls
    | initiate() on it, so adding a provider is a class plus a line here —
    | checkout itself does not change.
    |
    | Cambodia: ABA PayWay and Bakong KHQR are the realistic next options —
    | whichever your bank already supports. Both need merchant credentials
    | and signature verification on their webhooks before going live.
    |
    */

    'gateways' => [
        App\Payments\CodGateway::class,
    ],

    'default' => env('PAYMENT_DEFAULT', 'cod'),

];
