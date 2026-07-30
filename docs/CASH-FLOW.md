# Cash Flow

## Scope

The Cash Flow module is a direct-method accounting report generated from posted `journal_entries` and `journal_entry_lines`. It does not recalculate cash from orders, receipts, expenses, Stripe records, or DuitNow records. Those source records are used only to label and classify the journal-backed activity.

Draft and cancelled journals are excluded. Reversed journals and their posted reversals remain visible, so the financial history remains traceable and nets correctly.

## Cash accounts

In **Accounting → Chart of Accounts**, mark a debit-normal asset account as one of the following:

- Cash or bank account
- Cash equivalent
- Payment clearing account (also mark it as a cash equivalent)
- Include in Cash Flow reports

The default chart enables Cash on Hand (1000), Business Bank Account (1010), Stripe Clearing (1020), and DuitNow Clearing (1030). Administrators can exclude clearing accounts from a report when reviewing bank cash alone.

## Classification

Classification follows this order:

1. Posted journal source, including order, refund, payment fee, and owner compensation sources.
2. The editable mapping of the non-cash counter account in **Accounting → Cash Flow → Classifications**.
3. The counter account type and standard system-account code.
4. Unclassified, which is shown prominently in diagnostics.

Operating activity includes customer receipts, refunds, gateway fees, supplier and operating payments, and owner salary. Owner drawings and capital contributions are financing activity. Reserve allocations are non-cash equity transfers and never appear as cash movement unless an incorrect cash line is posted, which causes a diagnostic warning.

## Transfers and clearing accounts

Transfers between configured included cash accounts are marked **Internal Transfer**. They remain visible in individual-account movement history but are eliminated from the consolidated cash inflow and outflow totals.

With Stripe and DuitNow clearing enabled as cash equivalents, a customer receipt into clearing is reported once; a later clearing-to-bank settlement is an internal transfer. This prevents settlement double counting. Gateway fees and completed refunds are separate operating cash outflows.

## Reconciliation

The report calculates:

```text
Opening Cash + Net Cash Movement = Expected Closing Cash
Expected Closing Cash - General Ledger Closing Cash = Reconciliation Difference
```

For valid, fully classified posted activity the difference is RM0.00. The Diagnostics page is read-only and highlights unclassified cash lines, cash accounts missing Cash Flow inclusion, stale clearing balances, invalid internal transfers, Ledger integrity issues, and reconciliation differences. No data is adjusted automatically.

Opening balances configured on cash accounts are treated as approved opening balances. When their configured date is inside the selected period, they are displayed as a transparent opening-balance configuration movement so the report can reconcile without double counting a posted opening journal.

## Exports and audit trail

Administrators can export the statement, detailed movements, and a selected cash account as CSV, XLSX (when PHP Zip is available), or PDF. Print-friendly HTML is also available.

Exports, reconciliation access, diagnostics access, and mapping updates use the existing `accounting_audit_logs` table. The project currently authorizes accounting modules with the existing `auth` and `admin` middleware; it does not yet implement separate granular accounting permission records.

## Limitations

- The direct method is the authoritative Cash Flow report in this phase.
- There is no separate fixed-asset, loan, or bank-feed integration. Map those accounts manually under Cash Flow Classifications when the corresponding accounts exist.
- The existing legacy Profit & Loss page is not rewritten here; it may continue to use its established income/expense reporting path. Cash Flow remains journal-ledger based.
