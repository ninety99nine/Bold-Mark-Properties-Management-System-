<?php

namespace App\Enums;

enum PaymentType: string
{
    case NOT_SPECIFIED = 'not_specified';
    case EFT           = 'eft';
    case DEBIT_ORDER   = 'debit_order';
    case CASH          = 'cash';

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
     * Human-readable label (WeConnectU wording) for the Payment Type dropdown.
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
     * Value/label options for API + frontend selects.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
