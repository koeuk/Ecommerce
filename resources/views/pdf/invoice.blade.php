<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $isDeliveryNote ? 'Delivery Note' : 'Invoice' }} {{ $order->order_number }}</title>
    <style>
        /* dompdf has no flexbox, so this is deliberately table-based. */
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .wrap { padding: 28px 32px; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding-bottom: 18px; }
        .items th { background: #f3f4f6; text-align: left; padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #1f2937; padding-top: 8px; }
        .badge { display: inline-block; padding: 3px 8px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 10px; }
        .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 10px; }
    </style>
</head>
<body>
<div class="wrap">

    <table class="head">
        <tr>
            <td>
                <h1>{{ $shop['name'] }}</h1>
                <div class="muted">
                    {{ $shop['address'] }}<br>
                    {{ $shop['phone'] }} · {{ $shop['email'] }}
                </div>
            </td>
            <td class="right">
                <h1>{{ $isDeliveryNote ? 'DELIVERY NOTE' : 'INVOICE' }}</h1>
                <div class="muted">
                    <strong>{{ $order->order_number }}</strong><br>
                    {{ $order->placed_at?->format('d M Y') }}<br>
                    <span class="badge">{{ $order->status->label() }}</span>
                    <span class="badge">{{ ucfirst($order->payment_status->value) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="head">
        <tr>
            <td width="50%">
                <strong>Deliver to</strong><br>
                <span class="muted">
                    {{ $address['receiver_name'] ?? $order->customer_name }}<br>
                    {{ $address['address_line1'] ?? '' }}<br>
                    @if (!empty($address['address_line2'])){{ $address['address_line2'] }}<br>@endif
                    {{ $address['city'] ?? '' }}, {{ $address['state'] ?? '' }}<br>
                    {{ $address['phone'] ?? $order->customer_phone }}
                </span>
            </td>
            <td width="50%">
                <strong>Customer</strong><br>
                <span class="muted">
                    {{ $order->customer_name }}<br>
                    {{ $order->customer_phone }}<br>
                    {{ $order->customer_email }}
                </span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>Item</th>
            <th>SKU</th>
            <th class="right">Qty</th>
            @unless ($isDeliveryNote)
                <th class="right">Unit</th>
                <th class="right">Amount</th>
            @endunless
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>
                    {{ $item->product_name }}
                    @if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif
                </td>
                <td class="muted">{{ $item->sku }}</td>
                <td class="right">{{ $item->quantity }}</td>
                @unless ($isDeliveryNote)
                    <td class="right">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">${{ number_format((float) $item->subtotal, 2) }}</td>
                @endunless
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- A delivery note travels with the goods, so it carries no prices. --}}
    @unless ($isDeliveryNote)
        <table class="totals" style="margin-top: 14px;">
            <tr>
                <td></td>
                <td width="200">
                    <table>
                        <tr><td>Subtotal</td><td class="right">${{ number_format((float) $order->subtotal, 2) }}</td></tr>
                        @if ((float) $order->discount_total > 0)
                            <tr><td>Discount</td><td class="right">−${{ number_format((float) $order->discount_total, 2) }}</td></tr>
                        @endif
                        @if ((float) $order->tax_total > 0)
                            <tr><td>Tax</td><td class="right">${{ number_format((float) $order->tax_total, 2) }}</td></tr>
                        @endif
                        <tr><td>Shipping</td><td class="right">${{ number_format((float) $order->shipping_fee, 2) }}</td></tr>
                        <tr class="grand"><td>Total</td><td class="right">${{ number_format((float) $order->grand_total, 2) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @endunless

    @if ($order->customer_note)
        <p class="muted"><strong>Customer note:</strong> {{ $order->customer_note }}</p>
    @endif

    <div class="foot muted">
        @if ($isDeliveryNote)
            Received by ______________________  Date ____________
        @else
            Cash on Delivery — please have the exact amount ready. Thank you for your order.
        @endif
    </div>

</div>
</body>
</html>
