<?php

namespace App\Enums;

enum MandateType: string
{
    case BALANCE      = 'balance';
    case MONTHLY      = 'monthly';
    case MONTHLY_PLUS = 'monthly_plus';

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
     * Human-readable label (WeConnectU wording) for the debit-order mandate.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BALANCE      => 'Balance',
            self::MONTHLY      => 'Monthly',
            self::MONTHLY_PLUS => 'Monthly Plus',
        };
    }

    /**
     * Resolve a mandate type from the free-text bulk-upload column value.
     *
     * @param string|null $raw
     * @return self|null
     */
    public static function fromLabel(?string $raw): ?self
    {
        return match (mb_strtolower(trim((string) $raw))) {
            'balance'                    => self::BALANCE,
            'monthly'                    => self::MONTHLY,
            'monthly plus', 'monthly_plus' => self::MONTHLY_PLUS,
            default                      => null,
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
