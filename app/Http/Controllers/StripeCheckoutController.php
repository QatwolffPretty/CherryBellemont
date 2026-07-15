<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class StripeCheckoutController extends Controller
{
    public function start(Order $order, string $token, StripeCheckoutService $stripe): RedirectResponse
    {
        $this->authorizeGuest($order, $token);

        return $this->redirectToCheckout($order, $token, $stripe);
    }

    public function retry(Order $order, string $token, StripeCheckoutService $stripe): RedirectResponse
    {
        $this->authorizeGuest($order, $token);
        abort_unless($order->payment_method === 'stripe', 404);
        abort_if($order->payment_status === 'paid', 422, 'This order has already been paid.');
        abort_if($order->order_status === 'cancelled', 422, 'Cancelled orders cannot be paid again.');

        return $this->redirectToCheckout($order, $token, $stripe, true);
    }

    public function success(Request $request, StripeCheckoutService $stripe): View
    {
        $sessionId = (string) $request->query('session_id');
        abort_if($sessionId === '', 404);

        try {
            $session = $stripe->retrieveCheckoutSession($sessionId);
            $order = Order::query()->where('stripe_checkout_session_id', $session->id)->first();

            if (! $order) {
                $metadata = $session->metadata ?? null;
                $orderId = is_object($metadata) ? ($metadata->order_id ?? null) : ($metadata['order_id'] ?? null);
                $orderNumber = is_object($metadata) ? ($metadata->order_number ?? null) : ($metadata['order_number'] ?? null);
                $order = $orderId && $orderNumber
                    ? Order::query()->whereKey($orderId)->where('order_number', $orderNumber)->first()
                    : null;
            }
        } catch (Throwable $exception) {
            Log::warning('Stripe Checkout success page could not retrieve a session.', ['session_id' => $sessionId]);
            abort(404);
        }

        abort_unless($order && $order->payment_method === 'stripe', 404);

        return view('orders.stripe-success', compact('order', 'session'));
    }

    public function cancel(Order $order, string $token): View
    {
        $this->authorizeGuest($order, $token);
        abort_unless($order->payment_method === 'stripe', 404);

        return view('orders.stripe-cancel', compact('order', 'token'));
    }

    private function redirectToCheckout(Order $order, string $token, StripeCheckoutService $stripe, bool $retry = false): RedirectResponse
    {
        try {
            $session = $stripe->beginCheckout($order, $retry);
            session()->forget('stripe_pending_order');

            return redirect()->away($session->url);
        } catch (Throwable $exception) {
            Log::error('Unable to start Stripe Checkout.', [
                'order_number' => $order->order_number,
                'exception' => $exception,
            ]);

            try {
                $stripe->recordCheckoutFailure($order);
            } catch (Throwable $recordException) {
                Log::error('Unable to record a Stripe Checkout initialization failure.', [
                    'order_number' => $order->order_number,
                    'exception' => $recordException,
                ]);
            }

            session()->put('stripe_pending_order', [
                'order' => $order->order_number,
                'token' => $order->guest_access_token,
            ]);

            return to_route('checkout.create')
                ->withErrors(['stripe' => 'Stripe Checkout could not be started. Please try again.']);
        }
    }

    private function authorizeGuest(Order $order, string $token): void
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token), 403);
    }
}
