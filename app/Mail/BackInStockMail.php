<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\ProductStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProductStockNotification $notification,
        public readonly Product $product,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Back in Stock — '.$this->product->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.back-in-stock',
            text: 'emails.customer.back-in-stock-text',
            with: [
                'notification' => $this->notification,
                'product' => $this->product,
                'productUrl' => route('products.show', $this->product),
                'cancelUrl' => route('product-stock-notifications.cancel', ['token' => $this->notification->notification_token]),
            ],
        );
    }
}
