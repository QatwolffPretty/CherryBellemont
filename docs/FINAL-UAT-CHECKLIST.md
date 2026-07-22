# Final UAT Checklist

Run this checklist on the production-like URL over HTTPS before opening Cherry Bellemont to customers.

## Customer journey

- [ ] Home, Collection, About, FAQ, Contact, Shipping, Refund & Returns, Privacy, and Terms pages render correctly on desktop and mobile.
- [ ] A product with an image opens, the image is correctly sized, and an out-of-stock product shows Back-in-Stock instead of Add to Bag.
- [ ] Add to Bag, quantity updates, removal, and clear bag work without exceeding stock.
- [ ] WELCOMECHERRIES10 applies as a RM10 fixed discount, persists into checkout, and is removable.
- [ ] Standard delivery, express delivery, and self pickup calculate the expected totals.
- [ ] Release blocker: the RM30 Signature Gift Experience is not present in the current checkout source and must be implemented and tested in a separate milestone before it can be advertised.
- [ ] A guest can complete a DuitNow order, upload a valid receipt, and open only its own secure order URL.
- [ ] A Stripe test payment reaches Stripe Checkout and becomes paid only after the verified webhook.
- [ ] A paid customer can download an invoice. The invoice totals match the secure order page.
- [ ] Review, newsletter subscribe/unsubscribe, and Back-in-Stock request flows work.

## Admin journey

- [ ] An admin signs in at /login and is redirected to the admin dashboard.
- [ ] Every sidebar link opens: Dashboard, Products, Back-in-Stock, Reviews, Orders, Payment Verification, Shipping Zones, Delivery Methods, Coupons, Newsletter Subscribers, Newsletter Campaigns, FAQ, Customers, Reports, Settings, and View Store.
- [ ] Product image upload and stock editing work.
- [ ] A pending DuitNow receipt can be approved or rejected. Approval marks only the payment paid.
- [ ] A paid order can move through Processing, Packed, Shipped, and Delivered; shipping requires courier and tracking.
- [ ] Admin invoice and packing-slip downloads work.
- [ ] Coupon, FAQ, review, customer, report, newsletter subscriber, and campaign administration work.

## Release safeguards

- [ ] APP_ENV=production, APP_DEBUG=false, and a HTTPS APP_URL are configured.
- [ ] php artisan migrate --force, php artisan storage:link, cache commands, the queue worker, and the scheduler are running.
- [ ] Stripe is still using test keys unless live activation has been intentionally approved.
- [ ] php artisan queue:failed is reviewed; do not retry jobs that report deleted subscribers.
- [ ] Database and uploaded-file backups have completed successfully.
