# General Ledger

## Scope

The General Ledger is a read-only accounting report based on `accounting_accounts`, `journal_entries`, and `journal_entry_lines`. It does not calculate balances from orders, income forms, or expense forms directly. Those records appear only after their existing posting workflow creates a journal entry.

## Posted activity and reversals

Only entries with `posted` or `reversed` status are included. A reversed original stays visible as historical activity and its balancing reversal remains a separate posted entry. Together they produce the correct net result without deleting accounting history.

## Balance calculation

All calculations use integer sen internally.

- Debit-normal accounts: `opening + debits - credits`
- Credit-normal accounts: `opening + credits - debits`

The account ledger calculates the carried-forward balance before slicing its result into pages, so the running balance remains correct on every page.

Configured account opening balances are treated as a baseline from their `opening_balance_date`. When the date falls inside the selected period, the report shows one clearly labelled opening-balance row. If a posted `opening_balance` journal already exists for an account, the configured field is ignored by ledger calculations to prevent double counting; the integrity panel flags that configuration for review.

Parent accounts display direct postings only. Child accounts are displayed separately, so summary totals do not double count a hierarchy.

## Reports and exports

The General Ledger contains:

- Account summary, filters, and date shortcuts
- Per-account transaction history and running balance
- Trial Balance using the same filters and balance treatment
- Read-only integrity checks
- CSV, XLSX, PDF, and print-friendly report outputs

XLSX uses the project’s existing lightweight writer and needs the PHP Zip extension. CSV and PDF remain available when Zip is unavailable.

## Integrity checks

The integrity page reports, without modifying data:

- unbalanced posted journals;
- journals with fewer than two lines;
- invalid debit/credit lines;
- use of inactive accounts in historical postings;
- duplicate source indicators;
- missing reversal links;
- potential duplicate opening-balance treatment; and
- a ledger-level debit/credit difference.

Corrections must use the existing journal reversal or approved adjustment workflow. The report never inserts balancing lines automatically.

## Legacy Profit & Loss

The existing Profit & Loss page remains unchanged in this phase. It still uses the project’s legacy `AccountingReportService` calculation path. Future accounting reports should move to the General Ledger service only after a separately tested migration of historical reporting rules.

## Development checks

Run the normal migrations, then:

```powershell
php artisan migrate
php artisan view:cache
php artisan test tests/Feature/GeneralLedgerTest.php
```

No destructive migration or data reset is required.
