@component('mail::message')
# {{ $heading }}

{{ $intro }}

**{{ __('Order') }}:** {{ $order->order_number }}
**{{ __('Placed') }}:** {{ $order->placed_at?->format('d M Y H:i') }}

@component('mail::table')
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Price') }} |
|:-----------------|:--------------:|------------------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_label ? ' — '.$item->variant_label : '' }} | {{ $item->quantity }} | ${{ number_format((float) $item->subtotal, 2) }} |
@endforeach
@endcomponent

**{{ __('Subtotal') }}:** ${{ number_format((float) $order->subtotal, 2) }}
@if ((float) $order->discount_total > 0)
**{{ __('Discount') }}:** −${{ number_format((float) $order->discount_total, 2) }}
@endif
**{{ __('Shipping') }}:** ${{ number_format((float) $order->shipping_fee, 2) }}
**{{ __('Total') }}:** **${{ number_format((float) $order->grand_total, 2) }}**

@if ($order->shipping_address)
### {{ __('Delivering to') }}

{{ $order->shipping_address['receiver_name'] ?? '' }}
{{ $order->shipping_address['address_line1'] ?? '' }}
{{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }}
{{ $order->shipping_address['phone'] ?? '' }}
@endif

@if ($stage === 'placed')
{{ __('This is a Cash on Delivery order — please have the exact amount ready.') }}
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
@endcomponent
