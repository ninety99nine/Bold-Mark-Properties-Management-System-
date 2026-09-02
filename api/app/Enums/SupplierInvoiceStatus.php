<?php

namespace App\Enums;

/**
 * The lifecycle status of a supplier invoice (GRV), mirroring WeConnectU's
 * Drafts / Created split. A draft is a work-in-progress that has not yet posted
 * to the supplier ledger; a created GRV is posted and aged. Defaults to CREATED.
 */
enum SupplierInvoiceStatus: string
{
    case DRAFT   = 'draft';
    case CREATED = 'created';

    /**
     * The exact human label shown in the UI.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT   => 'Draft',
            self::CREATED => 'Created',
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
