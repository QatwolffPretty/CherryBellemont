# Owner Compensation

Owner Compensation is an admin-only accounting workflow at **Admin → Accounting → Owner Compensation**. It reuses the existing `owner_transactions`, Chart of Accounts, Journal Entries, General Ledger and accounting audit log; it does not create a second bookkeeping system.

## Transaction types and journal treatment

| Type | Debit | Credit | Profit & Loss treatment |
| --- | --- | --- | --- |
| Owner Salary | Configured Owner Salary Expense | Selected Cash or Bank account | Operating expense; it appears in ledger-based P&L. |
| Owner Drawing | Configured Owner Drawings account | Selected Cash or Bank account | Equity withdrawal; never revenue or operating expense. |
| Capital Contribution | Selected Cash or Bank account | Configured Owner Capital account | Equity contribution; never revenue. |
| Business Reserve | Configured Retained Earnings account | Configured Business Reserve account | Equity reclassification; never an expense. |
| Emergency Reserve | Configured Retained Earnings account | Configured Emergency Reserve account | Equity reclassification; never an expense. |

The mapped account codes are maintained through the existing accounting settings: `owner_salary_account`, `owner_drawings_account`, `owner_capital_account`, `retained_earnings_account`, `business_reserve_account`, `emergency_reserve_account`, `default_cash_account`, and `default_bank_account`. No account ID comes from a hidden form field.

## Workflow and safeguards

1. Create and edit a **draft** with the date, type, amount, description, optional payment account, reference, notes and private attachment.
2. Review the informational posting preview.
3. Post the draft. The application resolves active system mappings, generates a balanced Journal Entry, and marks the transaction immutable in one database transaction.
4. If correction is required, reverse the posted transaction. The original and reversal journals stay visible in the General Ledger.

Only drafts can be edited or cancelled. Cancelled drafts never create a journal. A posted record cannot be deleted or posted twice.

Owner Drawings are blocked when the selected Cash or Bank account would go below its posted General Ledger balance. Reserve allocations are blocked when posted Retained Earnings are insufficient. This project uses only the existing `is_admin` middleware and has no separate elevated override role, so balance overrides are intentionally unavailable.

## Attachments and exports

Attachments accept PDF, JPG, JPEG, PNG and WEBP files up to 5 MB. They are stored on Laravel's private `local` disk beneath `accounting/owner-compensation/{year}` and are available only through the authenticated admin download route.

The list supports CSV, XLSX (when the PHP Zip extension is installed), PDF and print exports. Attachments and their paths are never included in export rows.

## Reporting limitation

Owner Salary is present in posted journals and the General Ledger immediately. The existing Profit & Loss page remains on its legacy Income/Expense reporting path until it is intentionally migrated to ledger-based totals in a future phase; this module does not create duplicate expense records merely to change that page.

## Migration and test commands

```powershell
php artisan migrate
php artisan optimize:clear
php artisan view:cache
php artisan test tests/Feature/OwnerCompensationTest.php
```

No external service, payment workflow, Stripe, DuitNow or storefront behaviour is changed by this module.
