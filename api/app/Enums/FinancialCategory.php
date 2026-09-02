<?php

namespace App\Enums;

/**
 * WeConnectU General Ledger "Financial Category" — classifies each chart-of-accounts
 * line for reporting (Trial Balance / Income Statement / Balance Sheet) AND identifies
 * the double-entry control accounts. The GL posting engine resolves control accounts
 * by category (not by hard-coded code): Accounts Receivable = customer control,
 * Accounts Payable = supplier control, VAT Control = VAT, Bank = a cashbook ledger,
 * Retained Income = opening equity.
 *
 * Accounts Payable, Accounts Receivable and Retained Income are singletons — only one
 * GL account per organisation may carry that category (WeConnectU enforces "only 1 GL
 * account permitted for this Category").
 */
enum FinancialCategory: string
{
    case ACCOUNTS_PAYABLE      = 'accounts_payable';
    case ACCOUNTS_RECEIVABLE   = 'accounts_receivable';
    case BANK                  = 'bank';
    case COST_OF_SALES         = 'cost_of_sales';
    case CURRENT_ASSETS        = 'current_assets';
    case CURRENT_LIABILITIES   = 'current_liabilities';
    case DIVIDENDS             = 'dividends';
    case EQUITY_RESERVES       = 'equity_reserves';
    case EXPENSES              = 'expenses';
    case FIXED_ASSETS          = 'fixed_assets';
    case INVENTORY             = 'inventory';
    case INVESTMENTS           = 'investments';
    case LONG_TERM_BORROWINGS  = 'long_term_borrowings';
    case LONG_TERM_LIABILITIES = 'long_term_liabilities';
    case OTHER_FIXED_ASSETS    = 'other_fixed_assets';
    case OTHER_INCOME          = 'other_income';
    case RETAINED_INCOME       = 'retained_income';
    case SALES                 = 'sales';
    case SHARE_CAPITAL         = 'share_capital';
    case SHAREHOLDERS_LOAN     = 'shareholders_loan';
    case TAX                   = 'tax';
    case TAXATION              = 'taxation';
    case UNDEFINED             = 'undefined';
    case VAT_CONTROL           = 'vat_control';

    /**
     * The exact human label shown in the WeConnectU dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ACCOUNTS_PAYABLE      => 'Accounts Payable',
            self::ACCOUNTS_RECEIVABLE   => 'Accounts Receivable',
            self::BANK                  => 'Bank',
            self::COST_OF_SALES         => 'Cost of Sales',
            self::CURRENT_ASSETS        => 'Current Assets',
            self::CURRENT_LIABILITIES   => 'Current Liabilities',
            self::DIVIDENDS             => 'Dividends',
            self::EQUITY_RESERVES       => 'Equity & Reserves',
            self::EXPENSES              => 'Expenses',
            self::FIXED_ASSETS          => 'Fixed Assets',
            self::INVENTORY             => 'Inventory',
            self::INVESTMENTS           => 'Investments',
            self::LONG_TERM_BORROWINGS  => 'Long Term Borrowings',
            self::LONG_TERM_LIABILITIES => 'Long Term Liabilities',
            self::OTHER_FIXED_ASSETS    => 'Other Fixed Assets',
            self::OTHER_INCOME          => 'Other Income',
            self::RETAINED_INCOME       => 'Retained Income',
            self::SALES                 => 'Sales',
            self::SHARE_CAPITAL         => 'Share Capital',
            self::SHAREHOLDERS_LOAN     => 'Shareholders Loan',
            self::TAX                   => 'Tax',
            self::TAXATION              => 'Taxation',
            self::UNDEFINED             => 'Undefined',
            self::VAT_CONTROL           => 'VAT Control',
        };
    }

    /**
     * Categories of which only one GL account may exist per organisation.
     *
     * @return array<self>
     */
    public static function singletons(): array
    {
        return [self::ACCOUNTS_PAYABLE, self::ACCOUNTS_RECEIVABLE, self::RETAINED_INCOME];
    }

    /**
     * Whether this category is a singleton (only one GL account permitted).
     *
     * @return bool
     */
    public function isSingleton(): bool
    {
        return in_array($this, self::singletons(), true);
    }

    /**
     * Whether accounts in this category appear on the Income Statement (vs Balance Sheet).
     *
     * @return bool
     */
    public function isIncomeStatement(): bool
    {
        return in_array($this, [
            self::SALES, self::OTHER_INCOME, self::EXPENSES,
            self::COST_OF_SALES, self::TAX, self::TAXATION, self::DIVIDENDS,
        ], true);
    }

    /**
     * Return all enum values as a plain array (used in migrations).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return [value => label] option pairs for the UI dropdown, in display order.
     *
     * @return array<array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
