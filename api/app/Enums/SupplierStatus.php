<?php

namespace App\Enums;

/**
 * The verification status of a supplier, mirroring WeConnectU's Status
 * dropdown. The backed value is a stable slug; label() returns the exact human
 * string shown in the UI. Defaults to VERIFIED.
 */
enum SupplierStatus: string
{
    case VERIFIED   = 'verified';
    case UNVERIFIED = 'unverified';
    case PENDING    = 'pending';

    /**
     * The exact human label shown in the UI dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::VERIFIED   => 'Verified',
            self::UNVERIFIED => 'Unverified',
            self::PENDING    => 'Pending',
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
