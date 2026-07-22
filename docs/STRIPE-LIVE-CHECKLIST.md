# Stripe live-mode checklist

Stripe remains in **test mode** until every item below is completed manually.

## Before switching keys

1. Confirm the production website uses HTTPS and APP_URL is the public HTTPS URL.
2. Complete an end-to-end test-mode checkout, cancellation, retry, webhook, invoice, and customer email flow.
3. Confirm stripe/webhook is publicly reachable over HTTPS.
4. Confirm queue workers are running so post-payment email is not delayed indefinitely.
5. Confirm the current code validates MYR amounts and currency against server-side order totals.

## Switch to live mode manually

1. In Stripe Dashboard, switch to live mode.
2. Create a live webhook endpoint at https://your-domain.example/stripe/webhook.
3. Subscribe it to checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, payment_intent.succeeded, payment_intent.payment_failed, and charge.refunded.
4. Add the live values only to the production .env: STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, and STRIPE_CURRENCY=myr.
5. Run php artisan config:cache and php artisan queue:restart.
6. Place a low-value live order and verify the webhook marks only that order as paid.

## Safety rules

- Never put a secret key or webhook signing secret in a Blade view, JavaScript bundle, commit, or campaign.
- The success return page does not mark an order paid; only the verified webhook does.
- Duplicate webhooks are recorded and handled idempotently.
- A failed or mismatched payment requires investigation; it must not be marked paid manually without verification.
