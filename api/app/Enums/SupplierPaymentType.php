<?php

namespace App\Enums;

/**
 * How a supplier is paid, mirroring WeConnectU's Payment Type dropdown. The
 * backed value is a stable slug; label() returns the exact human string shown
 * in the UI. Defaults to NOT_SPECIFIED.
 */
enum SupplierPaymentType: string
{
    case NOT_SPECIFIED = 'not_specified';
    case EFT           = 'eft';
    case DEBIT_ORDER   = 'debit_order';
    case CASH          = 'cash';

    /**
     * The exact human label shown in the UI dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NOT_SPECIFIED => 'Not Specified',
            self::EFT           => 'EFT',
            self::DEBIT_ORDER   => 'Debit Order',
            self::CASH          => 'Cash',
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
