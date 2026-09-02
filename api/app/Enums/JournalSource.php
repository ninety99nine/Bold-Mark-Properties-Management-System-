<?php

namespace App\Enums;

/**
 * The origin of a journal batch. WeConnectU's GL holds transactions from many
 * sources; the "Journals" page shows only manually-created batches, while all
 * reports (Trial Balance, Detailed GL, Income Statement, VAT 201) read every
 * source. Auto-postings carry a source + source_type/source_id back-reference to
 * their originating document so they can be reposted or reversed idempotently.
 */
enum JournalSource: string
{
    case MANUAL          = 'manual';
    case INVOICE         = 'invoice';
    case CREDIT_NOTE     = 'credit_note';
    case CASHBOOK        = 'cashbook';
    case SUPPLIER_INVOICE = 'supplier_invoice';
    case OPENING         = 'opening';

    /**
     * A short human label for the source.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL           => 'Manual Journal',
            self::INVOICE          => 'Invoice',
            self::CREDIT_NOTE      => 'Credit Note',
            self::CASHBOOK         => 'Cashbook',
            self::SUPPLIER_INVOICE => 'Supplier Invoice',
            self::OPENING          => 'Opening Balance',
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
}
