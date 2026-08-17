<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    |
    | Each entry implements App\Payments\PaymentGateway. Checkout resolves
    | them through GatewayRegistry and never names one directly, so adding a
    | provider is a class plus a line here.
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
