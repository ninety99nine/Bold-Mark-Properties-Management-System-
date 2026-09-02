<?php

namespace App\Enums;

/**
 * The legal-entity type of a supplier, mirroring WeConnectU's Supplier Type
 * dropdown. The backed value is a stable slug; label() returns the exact human
 * string shown in the UI.
 */
enum SupplierType: string
{
    case BODY_CORPORATE      = 'body_corporate';
    case CASH_ONLY           = 'cash_only';
    case CLOSED_CORPORATION  = 'closed_corporation';
    case INCORPORATED        = 'incorporated';
    case INDIVIDUAL          = 'individual';
    case NON_PROFIT          = 'non_profit';
    case PARTNERSHIP         = 'partnership';
    case PRIVATE_COMPANY     = 'private_company';
    case PUBLIC_COMPANY      = 'public_company';
    case TRUST               = 'trust';

    /**
     * The exact human label shown in the UI dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BODY_CORPORATE     => 'Body Corporate',
            self::CASH_ONLY          => 'Cash Only',
            self::CLOSED_CORPORATION => 'Closed Corporation (CC)',
            self::INCORPORATED       => 'Incorporated',
            self::INDIVIDUAL         => 'Individual',
            self::NON_PROFIT         => 'Non Profit Organization',
            self::PARTNERSHIP        => 'Partnership',
            self::PRIVATE_COMPANY    => 'Private Company (Pty) Ltd',
            self::PUBLIC_COMPANY     => 'Public Company Ltd',
            self::TRUST              => 'Trust',
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
