<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeCheckoutService
{
    public function beginCheckout(Order $order, bool $retry = false): object
    {
        $session = $this->createCheckoutSession($order, $retry);

        if (! isset($session->id) || ! is_string($session->id) || $session->id === '') {
            throw new RuntimeException('Stripe Checkout did not return a valid session ID.');
        }

        if (! isset($session->url) || ! is_string($session->url) || ! filter_var($session->url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Stripe Checkout did not return a valid redirect URL.');
        }

        $order->update([
            'payment_provider' => 'stripe',
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_status' => $session->payment_status ?? 'unpaid',
            'stripe_failure_reason' => null,
        ]);

        return $session;
    }

    public function recordCheckoutFailure(Order $order): void
    {
        $order->update([
            'payment_provider' => 'stripe',
            'stripe_payment_status' => 'failed',
            'stripe_failure_reason' => 'Unable to start Stripe Checkout. Please try again.',
        ]);
    }

    public function createCheckoutSession(Order $order, bool $retry = false): object
    {
        return $this->client()->checkout->sessions->create($this->checkoutPayload($order), [
            'idempotency_key' => $retry
                ? 'cb-stripe-retry-'.$order->id.'-'.Str::uuid()
                : 'cb-stripe-checkout-'.$order->id,
        ]);
    }

    public function checkoutPayload(Order $order): array
    {
        $order->loadMissing('items');
        $discountSen = $this->amountInSen($order->discount_amount ?? 0);
        $shippingSen = $this->amountInSen($order->shipping_fee);
        $freeShippingSen = $this->amountInSen($order->free_shipping_discount ?? 0);
        $giftWrappingSen = $this->amountInSen($order->gift_wrapping_fee ?? 0);
        $netShippingSen = max(0, $shippingSen - $freeShippingSen);

        $lineItems = $discountSen > 0 ? [[
            'price_data' => [
                'currency' => $this->currency(),
                'unit_amount' => max(0, $this->amountInSen($order->subtotal) - $discountSen),
                'product_data' => ['name' => 'Collection items'.($order->coupon_code ? ' - '.$order->coupon_code.' applied' : '')],
            ],
            'quantity' => 1,
        ]] : $order->items->map(function ($item): array {
            return [
                'price_data' => [
                    'currency' => $this->currency(),
                    'unit_amount' => $this->amountInSen($item->unit_price),
                    'product_data' => ['name' => $item->product_name ?? $item->name],
                ],
                'quantity' => $item->quantity,
            ];
        })->values()->all();

        if ($netShippingSen > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->currency(),
                    'unit_amount' => $netShippingSen,
                    'product_data' => ['name' => 'Shipping — '.($order->shipping_method_name ?? 'Delivery')],
                ],
                'quantity' => 1,
            ];
        }

        if ($giftWrappingSen > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $this->currency(),
                    'unit_amount' => $giftWrappingSen,
                    'product_data' => ['name' => 'Cherry Bellemont Signature Gift Experience'],
                ],
                'quantity' => 1,
            ];
        }

        $payloadTotalSen = collect($lineItems)->sum(fn (array $line): int => $line['price_data']['unit_amount'] * $line['quantity']);
        if ($payloadTotalSen !== $this->amountInSen($order->total)) {
            throw new RuntimeException('Stripe Checkout line items did not match the order total.');
        }

        return [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'client_reference_id' => $order->order_number,
            'metadata' => [
                'order_number' => $order->order_number,
                'order_id' => (string) $order->id,
                'payment_provider' => 'stripe',
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_id' => (string) $order->id,
                    'payment_provider' => 'stripe',
                ],
            ],
            'success_url' => $this->successUrl(),
            'cancel_url' => route('stripe.cancel', [
                'order' => $order->order_number,
                'token' => $order->guest_access_token,
            ], true),
        ];
    }

    public function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, []);
    }

    public function findCheckoutSessionForPaymentIntent(string $paymentIntentId): ?object
    {
        $sessions = $this->client()->checkout->sessions->all([
            'payment_intent' => $paymentIntentId,
            'limit' => 1,
        ]);

        return $sessions->data[0] ?? null;
    }

    public function constructWebhookEvent(string $payload, ?string $signature): Event
    {
        if (! $signature || ! config('stripe.webhook_secret')) {
            throw new RuntimeException('Stripe webhook verification is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, config('stripe.webhook_secret'));
    }

    public function amountInSen(mixed $amount): int
    {
        $amount = trim((string) $amount);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new RuntimeException('Invalid monetary amount.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public function currency(): string
    {
        return strtolower((string) config('stripe.currency', 'myr'));
    }

    private function client(): StripeClient
    {
        $secret = config('stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($secret);
    }

    private function successUrl(): string
    {
        return route('stripe.success', [], true).'?session_id={CHECKOUT_SESSION_ID}';
    }
}
