<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use App\Models\StripeWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StripeWebhookService
{
    public function __construct(
        private readonly StripeCheckoutService $stripe,
        private readonly OrderNotifier $notifier,
        private readonly AdminNotificationService $adminNotifier,
        private readonly RefundService $refunds,
        private readonly ReturnNotifier $returnNotifier,
        private readonly AccountingPostingService $accounting,
    ) {}

    public function process(object $event, array $payload): void
    {
        $record = $this->eventRecord($event, $payload);
        $object = $event->data->object ?? null;

        try {
            $completedRefund = null;
            $approvedOrder = DB::transaction(function () use ($record, $event, $object, &$completedRefund): ?Order {
                $record = StripeWebhookEvent::query()->lockForUpdate()->findOrFail($record->id);
                if ($record->processed_at) {
                    Log::info('Stripe webhook event already processed.', $this->context($event, $object, null, 'already processed'));

                    return null;
                }

                $approvedOrder = match ($event->type) {
                    'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->markSessionPaid($object),
                    'checkout.session.async_payment_failed' => $this->markSessionFailed($object),
                    'payment_intent.succeeded' => $this->markPaymentIntentSucceeded($object),
                    'payment_intent.payment_failed' => $this->markPaymentIntentFailed($object),
                    'refund.created', 'refund.updated' => $this->markStripeRefund($object, $completedRefund),
                    'charge.refunded' => $this->recordChargeRefunded($object),
                    default => null,
                };

                $record->update(['processed_at' => now(), 'processing_error' => null]);

                return $approvedOrder;
            });
        } catch (Throwable $exception) {
            $this->recordFailure($record, $exception);
            Log::error('Stripe webhook processing failed.', $this->context($event, $object, $exception instanceof StripePaymentVerificationException ? $exception->order : null, $exception->getMessage()) + [
                'exception_class' => $exception::class,
            ]);

            if ($exception instanceof StripePaymentVerificationException) {
                $this->adminNotifier->send('payment_attention', [
                    'order' => $exception->order,
                    'provider' => 'Stripe',
                    'summary' => $exception->getMessage(),
                    'reference' => $event->id ?? $this->identifier($object?->id ?? null),
                    'occurredAt' => now(),
                ]);
            }

            throw $exception;
        }

        Log::info('Stripe webhook processed.', $this->context($event, $object, $approvedOrder, $approvedOrder ? 'payment completed' : 'no payment update required'));

        if ($approvedOrder) {
            try {
                $this->accounting->postPaidOrder($approvedOrder);
            } catch (Throwable $exception) {
                Log::error('Confirmed Stripe payment could not be posted to accounting.', ['order_number' => $approvedOrder->order_number, 'exception_class' => $exception::class]);
            }
            $this->notifier->send($approvedOrder, 'payment_approved');
            $this->adminNotifier->send('stripe_payment_confirmed', ['order' => $approvedOrder]);
        }
        if ($completedRefund?->returnRequest) {
            $this->returnNotifier->customer($completedRefund->returnRequest, $completedRefund->status === 'succeeded' ? 'refund_succeeded' : 'refund_failed');
        }
    }

    /**
     * Shared, idempotent completion point for Checkout Session and PaymentIntent events.
     */
    public function markStripeOrderPaid(Order $order, int $receivedAmountSen, string $currency, ?string $paymentIntentId): ?Order
    {
        if ($order->payment_status === 'paid') {
            return null;
        }

        $expectedAmountSen = $this->stripe->amountInSen($order->total);
        if ($expectedAmountSen !== $receivedAmountSen) {
            throw new StripePaymentVerificationException(
                'Stripe payment amount did not match the order total.',
                $order,
                'Stripe payment amount verification failed.',
            );
        }

        if (strtolower($currency) !== $this->stripe->currency()) {
            throw new StripePaymentVerificationException(
                'Stripe payment currency did not match the configured currency.',
                $order,
                'Stripe payment currency verification failed.',
            );
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_provider' => 'stripe',
            'stripe_payment_status' => 'paid',
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_paid_at' => now(),
            'stripe_failure_reason' => null,
        ]);

        return $order;
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

    private function markSessionPaid(?object $session): ?Order
    {
        if (! $session || strtolower((string) ($session->payment_status ?? '')) !== 'paid') {
            Log::warning('Stripe Checkout Session was ignored because it is not paid.', [
                'checkout_session_id' => $this->identifier($session?->id ?? null),
                'payment_intent_id' => $this->identifier($session?->payment_intent ?? null),
                'reason' => 'Checkout Session payment_status is not paid.',
            ]);

            return null;
        }

        $order = $this->lockedOrderForSession($session);

        return $this->markStripeOrderPaid(
            $order,
            (int) ($session->amount_total ?? -1),
            (string) ($session->currency ?? ''),
            $this->identifier($session->payment_intent ?? null),
        );
    }

    private function markPaymentIntentSucceeded(?object $paymentIntent): ?Order
    {
        if (! $paymentIntent) {
            throw new StripePaymentVerificationException('Stripe did not provide a PaymentIntent payload.');
        }

        $intentId = $this->identifier($paymentIntent->id ?? null);
        $order = $this->lockedOrderForPaymentIntent($paymentIntent);

        if (! $order && $intentId) {
            $session = $this->stripe->findCheckoutSessionForPaymentIntent($intentId);
            if ($session) {
                return $this->markSessionPaid($session);
            }
        }

        if (! $order) {
            throw new StripePaymentVerificationException('No matching Stripe order was found for the PaymentIntent.');
        }

        return $this->markStripeOrderPaid(
            $order,
            (int) ($paymentIntent->amount_received ?? $paymentIntent->amount ?? -1),
            (string) ($paymentIntent->currency ?? ''),
            $intentId,
        );
    }

    private function markSessionFailed(?object $session): ?Order
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

    private function markPaymentIntentFailed(?object $paymentIntent): ?Order
    {
        $order = $this->lockedOrderForPaymentIntent($paymentIntent);
        if (! $order) {
            throw new StripePaymentVerificationException('No matching Stripe order was found for the failed PaymentIntent.');
        }

        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'failed',
                'payment_provider' => 'stripe',
                'stripe_payment_status' => $paymentIntent->status ?? 'failed',
                'stripe_payment_intent_id' => $this->identifier($paymentIntent->id ?? null),
                'stripe_failure_reason' => 'Stripe reported that the payment could not be completed.',
            ]);
        }

        return null;
    }

    private function markStripeRefund(?object $stripeRefund, ?Refund &$completedRefund): ?Order
    {
        $refundId = $this->identifier($stripeRefund?->id ?? null);
        $intentId = $this->identifier($stripeRefund?->payment_intent ?? null);
        if (! $refundId || ! $intentId) {
            throw new StripePaymentVerificationException('Stripe refund payload did not include a refund or PaymentIntent reference.');
        }

        $refund = Refund::query()
            ->where('stripe_refund_id', $refundId)
            ->orWhere(fn ($query) => $query->where('stripe_payment_intent_id', $intentId)->whereIn('status', ['processing', 'pending']))
            ->lockForUpdate()
            ->first();
        if (! $refund) {
            Log::warning('Stripe refund webhook was ignored because no internal refund was found.', [
                'stripe_refund_id' => $this->identifier($refundId),
                'payment_intent_id' => $this->identifier($intentId),
            ]);

            return null;
        }

        if (isset($stripeRefund->amount) && (int) $stripeRefund->amount !== $this->stripe->amountInSen($refund->amount)) {
            throw new StripePaymentVerificationException('Stripe refund amount did not match the approved refund amount.', $refund->order);
        }
        if (isset($stripeRefund->currency) && strtolower((string) $stripeRefund->currency) !== $this->stripe->currency()) {
            throw new StripePaymentVerificationException('Stripe refund currency did not match the configured currency.', $refund->order);
        }

        if (! $refund->stripe_refund_id) {
            $refund->update(['stripe_refund_id' => $refundId, 'processed_at' => now()]);
        }

        if (($stripeRefund->status ?? null) === 'succeeded') {
            $completedRefund = $this->refunds->confirm($refund->fresh());
        } elseif (in_array($stripeRefund->status ?? null, ['failed', 'canceled'], true)) {
            $completedRefund = $this->refunds->fail($refund->fresh(), 'Stripe reported that the refund could not be completed.');
        }

        return null;
    }

    private function recordChargeRefunded(?object $charge): ?Order
    {
        Log::info('Stripe charge.refunded received; refund.updated is used as the authoritative return-refund event.', [
            'payment_intent_id' => $this->identifier($charge?->payment_intent ?? null),
            'charge_id' => $this->identifier($charge?->id ?? null),
        ]);

        return null;
    }

    private function lockedOrderForSession(?object $session): Order
    {
        $sessionId = $this->identifier($session?->id ?? null);
        $order = $sessionId
            ? Order::query()->where('stripe_checkout_session_id', $sessionId)->lockForUpdate()->first()
            : null;

        if (! $order) {
            $order = $this->lockedOrderFromMetadata($session?->metadata ?? null);
        }
        if (! $order && ($orderNumber = $this->identifier($session?->client_reference_id ?? null))) {
            $order = Order::query()->where('order_number', $orderNumber)->where('payment_method', 'stripe')->lockForUpdate()->first();
        }

        if (! $order || $order->payment_method !== 'stripe') {
            throw new StripePaymentVerificationException('No matching Stripe order was found for the Checkout Session.');
        }

        return $order;
    }

    private function lockedOrderForPaymentIntent(?object $paymentIntent): ?Order
    {
        $intentId = $this->identifier($paymentIntent?->id ?? $paymentIntent?->payment_intent ?? null);
        $order = $intentId
            ? Order::query()->where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first()
            : null;

        return $order ?: $this->lockedOrderFromMetadata($paymentIntent?->metadata ?? null);
    }

    private function lockedOrderFromMetadata(mixed $metadata): ?Order
    {
        $orderId = is_object($metadata) ? ($metadata->order_id ?? null) : ($metadata['order_id'] ?? null);
        $orderNumber = is_object($metadata) ? ($metadata->order_number ?? null) : ($metadata['order_number'] ?? null);
        $provider = is_object($metadata) ? ($metadata->payment_provider ?? null) : ($metadata['payment_provider'] ?? null);

        if ($provider !== 'stripe' || ! $orderNumber) {
            return null;
        }

        $query = Order::query()->where('order_number', $orderNumber)->where('payment_method', 'stripe')->lockForUpdate();
        if ($orderId) {
            $query->whereKey($orderId);
        }

        return $query->first();
    }

    private function recordFailure(StripeWebhookEvent $record, Throwable $exception): void
    {
        StripeWebhookEvent::query()->whereKey($record->id)->update([
            'processing_error' => Str::limit($exception->getMessage(), 65535, ''),
        ]);

        if ($exception instanceof StripePaymentVerificationException && $exception->order && $exception->orderFailureReason) {
            Order::query()->whereKey($exception->order->id)->update([
                'stripe_failure_reason' => $exception->orderFailureReason,
            ]);
        }
    }

    private function context(object $event, ?object $object, ?Order $order, string $reason): array
    {
        $eventType = (string) ($event->type ?? '');
        $isCheckoutSession = str_starts_with($eventType, 'checkout.session.');

        return [
            'stripe_event_id' => $event->id ?? null,
            'event_type' => $eventType,
            'checkout_session_id' => $isCheckoutSession ? $this->identifier($object?->id ?? null) : null,
            'payment_intent_id' => $this->identifier($object?->payment_intent ?? ($isCheckoutSession ? null : $object?->id ?? null)),
            'order_number' => $order?->order_number,
            'order_found' => $order !== null,
            'reason' => $reason,
        ];
    }

    private function identifier(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_object($value) ? ($value->id ?? null) : null;
    }
}

class StripePaymentVerificationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?Order $order = null, public readonly ?string $orderFailureReason = null)
    {
        parent::__construct($message);
    }
}
