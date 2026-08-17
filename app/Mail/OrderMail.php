<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One mailable for the whole order lifecycle — the three notifications differ
 * only in subject and heading, so separate classes would be three copies of
 * the same template.
 *
 * Queued: `ShouldQueue` keeps SMTP out of the request cycle. That only pays
 * off once QUEUE_CONNECTION is off `sync` (see Phase 8 config items).
 */
class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $stage,          // placed | shipped | delivered | cancelled
    ) {}

    public function envelope(): Envelope
    {
        $number = $this->order->order_number;

        return new Envelope(
            subject: match ($this->stage) {
                'shipped' => __('Your order :number is on its way', ['number' => $number]),
                'delivered' => __('Your order :number has been delivered', ['number' => $number]),
                'cancelled' => __('Your order :number has been cancelled', ['number' => $number]),
                default => __('We have received your order :number', ['number' => $number]),
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.status',
            with: [
                'order' => $this->order,
                'stage' => $this->stage,
                'heading' => $this->heading(),
                'intro' => $this->intro(),
            ],
        );
    }

    private function heading(): string
    {
        return match ($this->stage) {
            'shipped' => __('Your order is on its way'),
            'delivered' => __('Your order has been delivered'),
            'cancelled' => __('Your order has been cancelled'),
            default => __('Thank you for your order'),
        };
    }

    private function intro(): string
    {
        return match ($this->stage) {
            'shipped' => __('It has left our warehouse and is with the courier.'),
            'delivered' => __('We hope everything arrived in good order.'),
            'cancelled' => __('The items have been returned to stock. No payment is due.'),
            default => __('We will contact you to confirm delivery. Payment is on delivery.'),
        };
    }
}
