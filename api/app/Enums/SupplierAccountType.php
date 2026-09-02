<?php

namespace App\Enums;

/**
 * The supplier bank-account type, mirroring WeConnectU's Account Type dropdown.
 * The backed value is a stable slug; label() returns the exact human string
 * shown in the UI.
 */
enum SupplierAccountType: string
{
    case CURRENT    = 'current';
    case SAVINGS    = 'savings';
    case INVESTMENT = 'investment';

    /**
     * The exact human label shown in the UI dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CURRENT    => 'Current',
            self::SAVINGS    => 'Savings',
            self::INVESTMENT => 'Investment',
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
