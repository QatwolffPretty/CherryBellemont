# Returns, Exchanges and Refunds Operations

Cherry Bellemont reviews aftercare requests independently from payment and fulfilment status. A return request is not an automatic refund, exchange, cancellation, or stock adjustment.

## Current operational rules

- A customer must use a paid, delivered order and the secure guest-order token (or their authenticated account) to submit a request.
- The configured request window is 14 days from delivery. It is set in `config/store.php` under `returns.return_window_days`.
- Admin review follows: requested → under review → approved or rejected → awaiting return → item received → inspecting → resolution pending → completed or closed.
- Stock is restored only after a passed inspection where an administrator records the item disposition as `restocked`. Damaged, written-off, supplier-returned, and not-returned items do not restore stock.
- Refunds use the stored order-item price and coupon snapshots. Shipping and gift-wrapping refunds are never automatic; an administrator must record any approved amount explicitly.
- Stripe refunds are permitted only with a test-mode Stripe secret in this build. Their final state comes from a verified Stripe webhook. DuitNow refunds require a private transfer proof and reference before confirmation.

## Business-owner confirmation before production use

Confirm and update the published policy before launch if any of these differ from the business decision:

- the return window length;
- which change-of-mind cases are eligible;
- who pays return delivery costs;
- whether shipping or Signature Gift Experience fees can be refunded;
- exchange availability and replacement-order handling;
- any legally required consumer-rights wording for the selling jurisdiction.

The public Refund & Returns Policy intentionally does not promise automatic refunds. Any policy change should be checked against these operational rules before publication.
