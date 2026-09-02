<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CURRENT    = 'current';
    case SAVINGS    = 'savings';
    case INVESTMENT = 'investment';

    /**
     * The WeConnectU-facing label (title-cased) for this account type.
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
     * Return { value, label } option sets for the Cashbook form dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
