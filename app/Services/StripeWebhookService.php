<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StripeWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StripeWebhookService
{
    public function __construct(
        private readonly StripeCheckoutService $stripe,
        private readonly OrderNotifier $notifier,
    ) {}

    public function process(object $event, array $payload): void
    {
        $record = $this->eventRecord($event, $payload);
        $approvedOrder = DB::transaction(function () use ($record, $event): ?Order {
            $record = StripeWebhookEvent::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->processed_at) {
                return null;
            }

            try {
                $approvedOrder = match ($event->type) {
                    'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->markSessionPaid($event->data->object),
                    'checkout.session.async_payment_failed' => $this->markSessionFailed($event->data->object),
                    'payment_intent.payment_failed' => $this->markPaymentIntentFailed($event->data->object),
                    'charge.refunded' => $this->markChargeRefunded($event->data->object),
                    default => null,
                };

                $record->update(['processed_at' => now(), 'processing_error' => null]);

                return $approvedOrder;
            } catch (StripePaymentVerificationException $exception) {
                $record->update(['processed_at' => now(), 'processing_error' => $exception->getMessage()]);

                return null;
            }
        });

        if ($approvedOrder) {
            $this->notifier->send($approvedOrder, 'payment_approved');
        }
    }

    private function eventRecord(object $event, array $payload): StripeWebhookEvent
    {
        try {
            return StripeWebhookEvent::query()->firstOrCreate(
                ['stripe_event_id' => $event->id],
                ['event_type' => $event->type, 'payload' => $payload],
            );
        } catch (QueryException) {
            return StripeWebhookEvent::query()->where('stripe_event_id', $event->id)->firstOrFail();
        }
    }

    private function markSessionPaid(object $session): ?Order
    {
        $order = $this->lockedOrderForSession($session);
        $expectedAmount = $this->stripe->amountInSen($order->total);
        $receivedAmount = (int) ($session->amount_total ?? -1);

        if ($expectedAmount !== $receivedAmount) {
            $order->update(['stripe_failure_reason' => 'Stripe payment amount verification failed.']);
            throw new StripePaymentVerificationException('Stripe payment amount did not match the order total.');
        }

        if (strtolower((string) ($session->currency ?? '')) !== $this->stripe->currency()) {
            $order->update(['stripe_failure_reason' => 'Stripe payment currency verification failed.']);
            throw new StripePaymentVerificationException('Stripe payment currency did not match the configured currency.');
        }

        if ($order->payment_status === 'paid') {
            return null;
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_provider' => 'stripe',
            'stripe_payment_status' => $session->payment_status ?? 'paid',
            'stripe_payment_intent_id' => $this->identifier($session->payment_intent ?? null),
            'stripe_paid_at' => now(),
            'stripe_failure_reason' => null,
        ]);

        return $order;
    }

    private function markSessionFailed(object $session): ?Order
    {
        $order = $this->lockedOrderForSession($session);

        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'failed',
                'payment_provider' => 'stripe',
                'stripe_payment_status' => $session->payment_status ?? 'failed',
                'stripe_payment_intent_id' => $this->identifier($session->payment_intent ?? null),
                'stripe_failure_reason' => 'Stripe reported that the payment could not be completed.',
            ]);
        }

        return null;
    }

    private function markPaymentIntentFailed(object $paymentIntent): ?Order
    {
        $order = $this->lockedOrderForPaymentIntent($paymentIntent);
        if (! $order || $order->payment_status === 'paid') {
            return null;
        }

        $order->update([
            'payment_status' => 'failed',
            'payment_provider' => 'stripe',
            'stripe_payment_status' => $paymentIntent->status ?? 'failed',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'stripe_failure_reason' => 'Stripe reported that the payment could not be completed.',
        ]);

        return null;
    }

    private function markChargeRefunded(object $charge): ?Order
    {
        $order = $this->lockedOrderForPaymentIntent($charge);
        if (! $order) {
            return null;
        }

        $order->update([
            'payment_status' => 'refunded',
            'payment_provider' => 'stripe',
            'stripe_payment_status' => 'refunded',
            'stripe_payment_intent_id' => $this->identifier($charge->payment_intent ?? null),
            'stripe_failure_reason' => null,
        ]);

        return null;
    }

    private function lockedOrderForSession(object $session): Order
    {
        $order = Order::query()->where('stripe_checkout_session_id', $session->id)->lockForUpdate()->first();
        if (! $order) {
            $order = $this->lockedOrderFromMetadata($session->metadata ?? null);
        }

        if (! $order || $order->payment_method !== 'stripe') {
            throw new StripePaymentVerificationException('No matching Stripe order was found.');
        }

        return $order;
    }

    private function lockedOrderForPaymentIntent(object $paymentIntent): ?Order
    {
        $intentId = $this->identifier($paymentIntent->payment_intent ?? $paymentIntent->id ?? null);
        $order = $intentId ? Order::query()->where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first() : null;

        return $order ?: $this->lockedOrderFromMetadata($paymentIntent->metadata ?? null, false);
    }

    private function lockedOrderFromMetadata(mixed $metadata, bool $required = true): ?Order
    {
        $orderId = is_object($metadata) ? ($metadata->order_id ?? null) : ($metadata['order_id'] ?? null);
        $orderNumber = is_object($metadata) ? ($metadata->order_number ?? null) : ($metadata['order_number'] ?? null);
        $provider = is_object($metadata) ? ($metadata->payment_provider ?? null) : ($metadata['payment_provider'] ?? null);

        $order = $orderId && $orderNumber && $provider === 'stripe'
            ? Order::query()->whereKey($orderId)->where('order_number', $orderNumber)->lockForUpdate()->first()
            : null;

        if (! $order && $required) {
            throw new StripePaymentVerificationException('Stripe metadata did not identify a matching order.');
        }

        return $order;
    }

    private function identifier(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_object($value) ? ($value->id ?? null) : null;
    }
}

class StripePaymentVerificationException extends RuntimeException {}
