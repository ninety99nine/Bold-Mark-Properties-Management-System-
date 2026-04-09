<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case UNPAID         = 'unpaid';
    case PAID           = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case OVERDUE        = 'overdue';

    /**
     * Return all enum values as a plain array (used in migrations).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
