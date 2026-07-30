<?php

namespace App\Support;

class AccountingCatalog
{
    /** @return array<string, string> */
    public static function accountTypes(): array
    {
        return [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'revenue' => 'Revenue',
            'cost_of_goods_sold' => 'Cost of Goods Sold',
            'expense' => 'Expense',
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function subtypes(): array
    {
        return [
            'asset' => ['Cash', 'Bank', 'Payment Clearing', 'Clearing', 'Accounts Receivable', 'Receivable', 'Inventory', 'Prepaid Expense', 'Prepaid', 'Other Current Asset', 'Fixed Asset'],
            'liability' => ['Accounts Payable', 'Payable', 'Accrued Expense', 'Accrued', 'Tax Payable', 'Tax', 'Refund Payable', 'Refund', 'Other Current Liability', 'Owner'],
            'equity' => ['Owner Capital', 'Capital', 'Owner Drawings', 'Drawing', 'Retained Earnings', 'Current Year Earnings', 'Current Earnings', 'Business Reserve', 'Emergency Reserve', 'Reserve'],
            'revenue' => ['Product Sales', 'Sales', 'Shipping Income', 'Shipping', 'Gift Wrapping Income', 'Gift', 'Other Operating Income', 'Other', 'Sales Discounts', 'Sales Returns', 'Contra Revenue'],
            'cost_of_goods_sold' => ['Product Cost', 'COGS', 'Inventory Adjustment', 'Adjustment', 'Damaged Stock', 'Damaged'],
            'expense' => ['Payment Processing Fees', 'Payment Fees', 'Courier and Shipping', 'Shipping', 'Packaging', 'Marketing', 'Hosting', 'Domain', 'Software', 'Office', 'Utilities', 'Professional Services', 'Professional', 'Owner Salary', 'Staff Salary', 'Bank Charges', 'Bank', 'Other Operating Expense', 'Other'],
        ];
    }

    public static function defaultNormalBalance(string $type, ?string $subtype = null): string
    {
        if (in_array($subtype, ['Sales Discounts', 'Sales Returns', 'Contra Revenue', 'Owner Drawings', 'Drawing'], true)) {
            return 'debit';
        }

        return in_array($type, ['asset', 'expense', 'cost_of_goods_sold'], true) ? 'debit' : 'credit';
    }

    public static function allowsNormalBalance(string $type, ?string $subtype, string $balance): bool
    {
        return self::defaultNormalBalance($type, $subtype) === $balance
            || ($balance === 'credit' && $type === 'revenue' && in_array($subtype, ['Sales Discounts', 'Sales Returns', 'Contra Revenue'], true));
    }

    /** @return array<int, array<string, string|bool>> */
    public static function accounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'Cash', 'normal_balance' => 'debit', 'is_cash_account' => true, 'cash_flow_enabled' => true],
            ['code' => '1010', 'name' => 'Business Bank Account', 'type' => 'asset', 'subtype' => 'Bank', 'normal_balance' => 'debit', 'is_cash_account' => true, 'cash_flow_enabled' => true],
            ['code' => '1020', 'name' => 'Stripe Clearing Account', 'type' => 'asset', 'subtype' => 'Clearing', 'normal_balance' => 'debit', 'is_cash_equivalent' => true, 'is_clearing_account' => true, 'cash_flow_enabled' => true],
            ['code' => '1030', 'name' => 'DuitNow Clearing Account', 'type' => 'asset', 'subtype' => 'Clearing', 'normal_balance' => 'debit', 'is_cash_equivalent' => true, 'is_clearing_account' => true, 'cash_flow_enabled' => true],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'Receivable', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Inventory Asset', 'type' => 'asset', 'subtype' => 'Inventory', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'Prepaid', 'normal_balance' => 'debit'],
            ['code' => '1400', 'name' => 'Refund Receivable', 'type' => 'asset', 'subtype' => 'Receivable', 'normal_balance' => 'debit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'Payable', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'Accrued Expenses', 'type' => 'liability', 'subtype' => 'Accrued', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Customer Refunds Payable', 'type' => 'liability', 'subtype' => 'Refund', 'normal_balance' => 'credit'],
            ['code' => '2300', 'name' => 'Tax Payable', 'type' => 'liability', 'subtype' => 'Tax', 'normal_balance' => 'credit'],
            ['code' => '2400', 'name' => 'Owner Compensation Payable', 'type' => 'liability', 'subtype' => 'Owner', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner Capital', 'type' => 'equity', 'subtype' => 'Capital', 'normal_balance' => 'credit'],
            ['code' => '3100', 'name' => 'Owner Drawings', 'type' => 'equity', 'subtype' => 'Drawing', 'normal_balance' => 'debit'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'Retained Earnings', 'normal_balance' => 'credit'],
            ['code' => '3300', 'name' => 'Current Year Earnings', 'type' => 'equity', 'subtype' => 'Current Earnings', 'normal_balance' => 'credit'],
            ['code' => '3400', 'name' => 'Business Reserve', 'type' => 'equity', 'subtype' => 'Reserve', 'normal_balance' => 'credit'],
            ['code' => '3500', 'name' => 'Emergency Reserve', 'type' => 'equity', 'subtype' => 'Reserve', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Product Sales', 'type' => 'revenue', 'subtype' => 'Sales', 'normal_balance' => 'credit'],
            ['code' => '4010', 'name' => 'Shipping Income', 'type' => 'revenue', 'subtype' => 'Shipping', 'normal_balance' => 'credit'],
            ['code' => '4020', 'name' => 'Gift Wrapping Income', 'type' => 'revenue', 'subtype' => 'Gift', 'normal_balance' => 'credit'],
            ['code' => '4030', 'name' => 'Other Operating Income', 'type' => 'revenue', 'subtype' => 'Other', 'normal_balance' => 'credit'],
            ['code' => '4090', 'name' => 'Sales Discounts', 'type' => 'revenue', 'subtype' => 'Contra Revenue', 'normal_balance' => 'debit'],
            ['code' => '4100', 'name' => 'Sales Returns and Refunds', 'type' => 'revenue', 'subtype' => 'Contra Revenue', 'normal_balance' => 'debit'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'cost_of_goods_sold', 'subtype' => 'COGS', 'normal_balance' => 'debit'],
            ['code' => '5010', 'name' => 'Inventory Adjustments', 'type' => 'cost_of_goods_sold', 'subtype' => 'Adjustment', 'normal_balance' => 'debit'],
            ['code' => '5020', 'name' => 'Damaged Stock Expense', 'type' => 'cost_of_goods_sold', 'subtype' => 'Damaged', 'normal_balance' => 'debit'],
            ['code' => '6000', 'name' => 'Payment Processing Fees', 'type' => 'expense', 'subtype' => 'Payment Fees', 'normal_balance' => 'debit'],
            ['code' => '6010', 'name' => 'Courier and Shipping Expense', 'type' => 'expense', 'subtype' => 'Shipping', 'normal_balance' => 'debit'],
            ['code' => '6020', 'name' => 'Packaging Expense', 'type' => 'expense', 'subtype' => 'Packaging', 'normal_balance' => 'debit'],
            ['code' => '6030', 'name' => 'Marketing Expense', 'type' => 'expense', 'subtype' => 'Marketing', 'normal_balance' => 'debit'],
            ['code' => '6040', 'name' => 'Hosting Expense', 'type' => 'expense', 'subtype' => 'Hosting', 'normal_balance' => 'debit'],
            ['code' => '6050', 'name' => 'Domain Expense', 'type' => 'expense', 'subtype' => 'Domain', 'normal_balance' => 'debit'],
            ['code' => '6060', 'name' => 'Software Subscription Expense', 'type' => 'expense', 'subtype' => 'Software', 'normal_balance' => 'debit'],
            ['code' => '6070', 'name' => 'Office Expense', 'type' => 'expense', 'subtype' => 'Office', 'normal_balance' => 'debit'],
            ['code' => '6080', 'name' => 'Utilities Expense', 'type' => 'expense', 'subtype' => 'Utilities', 'normal_balance' => 'debit'],
            ['code' => '6090', 'name' => 'Professional Services', 'type' => 'expense', 'subtype' => 'Professional', 'normal_balance' => 'debit'],
            ['code' => '6100', 'name' => 'Owner Salary Expense', 'type' => 'expense', 'subtype' => 'Owner Salary', 'normal_balance' => 'debit'],
            ['code' => '6110', 'name' => 'Staff Salary Expense', 'type' => 'expense', 'subtype' => 'Staff Salary', 'normal_balance' => 'debit'],
            ['code' => '6120', 'name' => 'Bank Charges', 'type' => 'expense', 'subtype' => 'Bank', 'normal_balance' => 'debit'],
            ['code' => '6190', 'name' => 'Other Operating Expenses', 'type' => 'expense', 'subtype' => 'Other', 'normal_balance' => 'debit'],
        ];
    }

    /** @return array<string, string> */
    public static function defaultMappings(): array
    {
        return [
            'financial_year_start' => '01-01', 'default_currency' => 'MYR', 'default_bank_account' => '1010', 'default_cash_account' => '1000',
            'stripe_clearing_account' => '1020', 'duitnow_clearing_account' => '1030', 'product_sales_account' => '4000', 'shipping_income_account' => '4010',
            'gift_wrapping_income_account' => '4020', 'sales_discount_account' => '4090', 'refund_account' => '4100', 'inventory_asset_account' => '1200',
            'cost_of_goods_sold_account' => '5000', 'payment_processing_fee_account' => '6000', 'owner_salary_account' => '6100', 'owner_drawings_account' => '3100',
            'owner_capital_account' => '3000', 'business_reserve_account' => '3400', 'emergency_reserve_account' => '3500', 'retained_earnings_account' => '3200',
            'automatic_posting_enabled' => '1', 'require_expense_approval' => '1', 'journal_entry_prefix' => 'JE', 'opening_balance_date' => now()->startOfYear()->toDateString(),
        ];
    }

    /** @return array<string, string> */
    public static function expenseCategories(): array
    {
        return ['Inventory Purchases' => '1200', 'Shipping' => '6010', 'Packaging' => '6020', 'Marketing' => '6030', 'Hosting' => '6040', 'Domain' => '6050', 'Software' => '6060', 'Office' => '6070', 'Utilities' => '6080', 'Professional Services' => '6090', 'Owner Salary' => '6100', 'Staff Salary' => '6110', 'Bank Fees' => '6120', 'Other' => '6190'];
    }
}
