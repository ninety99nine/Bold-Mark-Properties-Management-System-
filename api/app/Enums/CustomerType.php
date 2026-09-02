<?php

namespace App\Enums;

enum CustomerType: string
{
    case BODY_CORPORATE           = 'body_corporate';
    case CASH_ONLY                = 'cash_only';
    case CLOSED_CORPORATION       = 'closed_corporation';
    case INCORPORATED             = 'incorporated';
    case INDIVIDUAL               = 'individual';
    case NON_PROFIT_ORGANIZATION  = 'non_profit_organization';
    case PARTNERSHIP              = 'partnership';
    case PRIVATE_COMPANY          = 'private_company';
    case PUBLIC_COMPANY           = 'public_company';
    case TRUST                    = 'trust';

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
     * Human-readable label (WeConnectU wording) for the Customer Type dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BODY_CORPORATE          => 'Body Corporate',
            self::CASH_ONLY               => 'Cash Only',
            self::CLOSED_CORPORATION      => 'Closed Corporation (CC)',
            self::INCORPORATED            => 'Incorporated',
            self::INDIVIDUAL              => 'Individual',
            self::NON_PROFIT_ORGANIZATION => 'Non Profit Organization',
            self::PARTNERSHIP             => 'Partnership',
            self::PRIVATE_COMPANY         => 'Private Company (Pty) Ltd',
            self::PUBLIC_COMPANY          => 'Public Company Ltd',
            self::TRUST                   => 'Trust',
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
