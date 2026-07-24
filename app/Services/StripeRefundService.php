<?php

namespace App\Services;

use App\Models\Refund;
use RuntimeException;
use Stripe\StripeClient;

class StripeRefundService
{
    public function create(Refund $refund): object
    {
        if (! $refund->stripe_payment_intent_id) throw new RuntimeException('The original Stripe PaymentIntent is unavailable.');
        return $this->client()->refunds->create([
            'payment_intent' => $refund->stripe_payment_intent_id,
            'amount' => (int) round((float) $refund->amount * 100),
            'metadata' => ['order_number' => $refund->order->order_number, 'return_number' => $refund->returnRequest?->return_number, 'refund_number' => $refund->refund_number],
        ], ['idempotency_key' => 'cb-refund-'.$refund->refund_number]);
    }

    private function client(): StripeClient
    {
        if (! $secret = config('stripe.secret')) throw new RuntimeException('Stripe is not configured.');
        if (! str_starts_with($secret, 'sk_test_')) throw new RuntimeException('Stripe refunds are restricted to test mode during development.');
        return new StripeClient($secret);
    }
}
