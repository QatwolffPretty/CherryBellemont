<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\CashFlowAccountMapping;
use Illuminate\Support\Collection;

/** Maintains editable, non-secret account classifications for Cash Flow. */
class CashFlowConfigurationService
{
    /** @var array<string, array{classification:string,category_key:string,label:string,display_order:int}> */
    private const DEFAULT_MAPPINGS = [
        '4000' => ['classification' => 'operating', 'category_key' => 'customer_receipts', 'label' => 'Cash Received from Customers', 'display_order' => 10],
        '4010' => ['classification' => 'operating', 'category_key' => 'customer_receipts', 'label' => 'Cash Received from Customers', 'display_order' => 10],
        '4020' => ['classification' => 'operating', 'category_key' => 'customer_receipts', 'label' => 'Cash Received from Customers', 'display_order' => 10],
        '4030' => ['classification' => 'operating', 'category_key' => 'other_operating_receipts', 'label' => 'Other Operating Receipts', 'display_order' => 20],
        '4100' => ['classification' => 'operating', 'category_key' => 'refunds', 'label' => 'Customer Refunds', 'display_order' => 30],
        '1200' => ['classification' => 'operating', 'category_key' => 'inventory_payments', 'label' => 'Inventory and Supplier Payments', 'display_order' => 40],
        '6000' => ['classification' => 'operating', 'category_key' => 'payment_fees', 'label' => 'Payment Gateway Fees', 'display_order' => 50],
        '6010' => ['classification' => 'operating', 'category_key' => 'courier', 'label' => 'Courier and Delivery', 'display_order' => 60],
        '6020' => ['classification' => 'operating', 'category_key' => 'packaging', 'label' => 'Packaging', 'display_order' => 70],
        '6030' => ['classification' => 'operating', 'category_key' => 'marketing', 'label' => 'Marketing', 'display_order' => 80],
        '6040' => ['classification' => 'operating', 'category_key' => 'hosting_domain', 'label' => 'Hosting and Domain', 'display_order' => 90],
        '6050' => ['classification' => 'operating', 'category_key' => 'hosting_domain', 'label' => 'Hosting and Domain', 'display_order' => 90],
        '6060' => ['classification' => 'operating', 'category_key' => 'software', 'label' => 'Software', 'display_order' => 100],
        '6070' => ['classification' => 'operating', 'category_key' => 'office', 'label' => 'Office Expenses', 'display_order' => 110],
        '6080' => ['classification' => 'operating', 'category_key' => 'utilities', 'label' => 'Utilities', 'display_order' => 120],
        '6100' => ['classification' => 'operating', 'category_key' => 'salary', 'label' => 'Owner Salary', 'display_order' => 130],
        '6110' => ['classification' => 'operating', 'category_key' => 'salary', 'label' => 'Salary Payments', 'display_order' => 130],
        '6120' => ['classification' => 'operating', 'category_key' => 'payment_fees', 'label' => 'Bank Charges', 'display_order' => 50],
        '6190' => ['classification' => 'operating', 'category_key' => 'other_operating_payments', 'label' => 'Other Operating Payments', 'display_order' => 140],
        '6190' => ['classification' => 'operating', 'category_key' => 'other_operating_payments', 'label' => 'Other Operating Payments', 'display_order' => 140],
        '3100' => ['classification' => 'financing', 'category_key' => 'owner_drawings', 'label' => 'Owner Drawings', 'display_order' => 10],
        '3000' => ['classification' => 'financing', 'category_key' => 'owner_capital', 'label' => 'Owner Capital Contributions', 'display_order' => 20],
        '3400' => ['classification' => 'non_cash', 'category_key' => 'reserve_transfer', 'label' => 'Business Reserve Allocation', 'display_order' => 10],
        '3500' => ['classification' => 'non_cash', 'category_key' => 'reserve_transfer', 'label' => 'Emergency Reserve Allocation', 'display_order' => 10],
    ];

    public function ensureDefaults(): void
    {
        $accounts = AccountingAccount::query()->whereIn('code', array_keys(self::DEFAULT_MAPPINGS))->get()->keyBy('code');
        foreach (self::DEFAULT_MAPPINGS as $code => $mapping) {
            if ($account = $accounts->get($code)) {
                CashFlowAccountMapping::query()->firstOrCreate(
                    ['accounting_account_id' => $account->id],
                    $mapping + ['is_active' => true],
                );
            }
        }
    }

    /** @return Collection<int, CashFlowAccountMapping> */
    public function mappings(): Collection
    {
        $this->ensureDefaults();

        return CashFlowAccountMapping::query()
            ->with('account:id,code,name,type,subtype')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /** @return array<string, string> */
    public function categoryLabels(): array
    {
        return [
            'customer_receipts' => 'Cash Received from Customers', 'other_operating_receipts' => 'Other Operating Receipts', 'refunds' => 'Customer Refunds',
            'payment_fees' => 'Payment Gateway Fees', 'inventory_payments' => 'Inventory and Supplier Payments', 'supplier_payments' => 'Supplier Payments',
            'courier' => 'Courier and Delivery', 'packaging' => 'Packaging', 'marketing' => 'Marketing', 'hosting_domain' => 'Hosting and Domain',
            'software' => 'Software', 'utilities' => 'Utilities', 'office' => 'Office Expenses', 'salary' => 'Owner Salary',
            'other_operating_payments' => 'Other Operating Payments', 'asset_purchase' => 'Asset Purchases', 'asset_sale' => 'Asset Sale Proceeds',
            'other_investing' => 'Other Investing Activities', 'owner_capital' => 'Owner Capital Contributions', 'owner_drawings' => 'Owner Drawings',
            'loan_received' => 'Loans Received', 'loan_repayment' => 'Loan Repayments', 'other_financing' => 'Other Financing Activities',
            'reserve_transfer' => 'Reserve Allocation (Non-Cash)', 'unclassified' => 'Unclassified Cash Movement', 'internal_transfer' => 'Internal Transfer',
        ];
    }
}
