# Cherry Bellemont Accounting Module — Phase 1

## Purpose

Accounting is separate from the existing **Reports** module. Reports remains the operational store-analytics area; Accounting provides an auditable double-entry bookkeeping foundation, sales summary, General Ledger, Profit & Loss, Cash Flow, Chart of Accounts, expenses, owner compensation, and financial mappings.

## Setup

Run the normal non-destructive migration and seed the missing default records:

```powershell
php artisan migrate
php artisan db:seed --class=AccountingSeeder
php artisan optimize:clear
```

The seeder uses `firstOrCreate`, so it adds only missing system accounts, expense categories, and mapping defaults. It does not replace customised account names, settings, or historical journals.

## Default Chart of Accounts

The initial Chart of Accounts contains system-managed accounts for cash and clearing (1000–1400), liabilities (2000–2400), equity (3000–3500), revenue (4000–4100), COGS (5000–5020), and expenses (6000–6190).

System accounts are required for automated postings. They cannot be deleted and can only be deactivated when they have no posted line activity and no active mapping.

## Automatic postings

Accounting reacts only after the existing payment/refund workflow confirms a financial event:

- **Stripe:** after a verified webhook marks an order paid.
- **DuitNow:** after an administrator approves a receipt and the payment becomes paid.
- **Refunds:** after a refund reaches `succeeded`.

For a paid order, Accounting creates a balanced sales journal:

- Debit the Stripe or DuitNow clearing account for the order total.
- Credit product sales, original shipping income, and gift wrapping income.
- Debit Sales Discounts for coupon and free-shipping discounts.
- When historical item cost is available, debit Cost of Goods Sold and credit Inventory Asset in a second balanced entry.

The unique `(source_type, source_id, source_event)` record makes repeated payment webhooks and retries idempotent. Accounting never changes an order’s payment status, stock, checkout totals, or fulfilment state.

## Cost of goods sold

`products.cost_price` is the editable current unit cost. At checkout, that value is copied into `order_items.unit_cost`. Accounting uses the order-item snapshot, not the current product cost, so historical gross profit is stable. Products created before this module have no historical cost until an administrator supplies it; their COGS is correctly shown as unavailable/zero rather than invented.

## Journals and corrections

Draft journals do not appear in the General Ledger, Profit & Loss, or Cash Flow.

Posted journals are immutable. Use **Reverse** to create an equal-and-opposite posted entry, then create an adjustment if needed. This preserves the audit trail.

## Owner transactions

- Owner Salary: debit Owner Salary Expense; credit the selected cash/bank account.
- Owner Drawing: debit Owner Drawings; credit cash/bank. It is not an operating expense.
- Owner Capital Contribution: debit cash/bank; credit Owner Capital.
- Reserve allocations: move balances between equity accounts. They are not expenses.

## Financial settings

Financial Settings map the system events to active accounts. Mappings are type-checked (for example, the Stripe clearing mapping must be an Asset account). Changes apply only to **future** automated postings; existing journals and invoices are never recalculated.

## Exports

General Ledger, Sales Summary, Profit & Loss, and Cash Flow provide CSV, XLSX, and PDF exports. The PHP Zip extension is required for XLSX output. Exported data uses the selected date range and report filters.

## Access and audit

All Accounting routes are inside the existing `auth` + `admin` route group. The project’s current permission model is admin/non-admin; the Accounting module follows it without changing authentication. Accounting actions record immutable audit rows with a minimised IP hash.

## Accounting assumptions requiring accountant confirmation

1. Revenue is recognised when the existing payment workflow confirms the order as paid.
2. Stripe and DuitNow amounts are initially posted to clearing accounts. Reconciliation and provider-fee imports are prepared through the payment-fee posting service, but provider settlements are not yet imported automatically.
3. Product cost is product-level; there is no variant-level inventory valuation in the current catalogue structure.
4. Tax is captured on manual expenses but no tax filing, SST/GST calculation, or tax-journal automation is included.
5. Refund COGS reversals occur only for returned items recorded as restocked with a historical unit-cost snapshot.

Review these policies with a qualified Malaysian accountant before relying on Accounting for statutory reporting.
