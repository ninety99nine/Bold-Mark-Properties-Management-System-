<?php

namespace App\Enums;

/**
 * The type of a supplier invoice (GRV), mirroring WeConnectU's Type filter
 * (Fixed Recurring / Variable Recurring / Ad-hoc / Invoice Assistant). A plain
 * once-off capture is ADHOC. Defaults to ADHOC.
 */
enum SupplierInvoiceType: string
{
    case ADHOC              = 'adhoc';
    case FIXED_RECURRING    = 'fixed_recurring';
    case VARIABLE_RECURRING = 'variable_recurring';
    case INVOICE_ASSISTANT  = 'invoice_assistant';

    /**
     * The exact human label shown in the UI.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ADHOC              => 'Adhoc',
            self::FIXED_RECURRING    => 'Fixed Recurring',
            self::VARIABLE_RECURRING => 'Variable Recurring',
            self::INVOICE_ASSISTANT  => 'Invoice Assistant',
        };
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
