# Go-Live Checklist

## Configuration

- [ ] Use APP_ENV=production, APP_DEBUG=false, a strong APP_KEY, secure HTTPS APP_URL, database credentials, and production mail credentials.
- [ ] Set QUEUE_CONNECTION=database (or the chosen supported queue), configure the queue worker, and configure the scheduler cron.
- [ ] Configure the live business DuitNow details and QR path only after reviewing them in a staging environment.
- [ ] Configure ADMIN_NOTIFICATION_EMAIL, LOW_STOCK_THRESHOLD, and production support and social values.

## Stripe transition

- [ ] Keep test keys and test webhook configuration while completing UAT.
- [ ] When live activation is approved, replace only STRIPE_KEY, STRIPE_SECRET, and STRIPE_WEBHOOK_SECRET with Stripe live values.
- [ ] Create an HTTPS webhook endpoint at /stripe/webhook.
- [ ] Subscribe it to checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, payment_intent.payment_failed, and charge.refunded.
- [ ] Complete a low-value live payment only after confirming the webhook signature and order status updates.

## Final checks

- [ ] Production assets are built and public/build/manifest.json exists.
- [ ] The public/hot file does not exist.
- [ ] Private payment receipts are not publicly accessible.
- [ ] Robots and sitemap do not expose admin, cart, checkout, login, or secure-order URLs.
- [ ] A current database backup and uploaded-file backup exist off-server.
- [ ] The release and rollback owners know how to monitor logs, failed jobs, and queues.
