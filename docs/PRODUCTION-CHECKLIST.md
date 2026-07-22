# Cherry Bellemont production checklist

## Before launch

- [ ] APP_ENV=production, APP_DEBUG=false, and APP_URL uses HTTPS.
- [ ] APP_KEY is set and production .env is not in source control.
- [ ] Database, cache, session, and queue drivers are configured and migrations are up to date.
- [ ] storage:link exists and public product/review/campaign images resolve.
- [ ] Existing payment receipts have been reviewed with receipts:secure-storage --dry-run.
- [ ] Queue worker and scheduler are running.
- [ ] Production mail sender is verified. Keep MAIL_MAILER=log only for local development.
- [ ] Stripe remains on test keys until docs/STRIPE-LIVE-CHECKLIST.md is complete.
- [ ] DuitNow QR asset and account details are replaced with verified business values.
- [ ] Backups for MySQL, public uploads, private uploads, and secrets are configured.

## Launch smoke test

- [ ] Homepage, Collection, product detail, mobile navigation, and 404 page.
- [ ] Shopping bag and guest checkout.
- [ ] Shipping quote, coupon, and RM30 gift wrapping where enabled.
- [ ] Stripe test card checkout, cancellation, retry, verified webhook, and payment email.
- [ ] DuitNow payment page, receipt upload, rejection, replacement, and admin approval.
- [ ] Admin login, product image upload, stock update, and fulfilment/tracking update.
- [ ] Paid invoice and admin packing slip download.
- [ ] Customer order page and guest-access token protection.
- [ ] Newsletter subscribe/unsubscribe, campaign test email, and scheduled campaign dispatch.
- [ ] Review submission, moderation, and back-in-stock request/cancellation.
- [ ] Admin dashboard, customers, reports, and CSV exports.
- [ ] Security headers, /robots.txt, /sitemap.xml, HTTPS redirect, and production-safe error pages.

## After deployment

- [ ] Check storage/logs/laravel.log and failed jobs after the first orders.
- [ ] Confirm no stack traces are shown to customers.
- [ ] Confirm email and payment webhooks have not recorded failures.
- [ ] Monitor disk usage for public assets, private receipts, logs, and database backups.
- [ ] Keep a rollback release and a tested restore path available.
