<?php

namespace App\Http\Controllers;

use App\Services\StripeCheckoutService;
use App\Services\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeCheckoutService $stripe, StripeWebhookService $webhooks): JsonResponse
    {
        $payload = $request->getContent();

        try {
            $event = $stripe->constructWebhookEvent($payload, $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|\UnexpectedValueException|\RuntimeException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        try {
            $webhooks->process($event, json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
