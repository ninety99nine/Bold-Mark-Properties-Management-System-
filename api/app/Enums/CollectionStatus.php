<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case NONE                = 'none';
    case REMINDER            = 'reminder';
    case FIRST_NOTICE        = 'first_notice';
    case SECOND_NOTICE       = 'second_notice';
    case FINAL_NOTICE        = 'final_notice';
    case LETTER_OF_DEMAND    = 'letter_of_demand';
    case PAYMENT_ARRANGEMENT = 'payment_arrangement';
    case HANDED_OVER         = 'handed_over';
    case PAID                = 'paid';

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
     * Human-readable label (WeConnectU wording) for the age-analysis status icon.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE                => '',
            self::REMINDER            => 'Reminder sent',
            self::FIRST_NOTICE        => '1st Notice',
            self::SECOND_NOTICE       => '2nd Notice',
            self::FINAL_NOTICE        => 'Final Notice',
            self::LETTER_OF_DEMAND    => 'Letter of Demand sent',
            self::PAYMENT_ARRANGEMENT => 'Payment Arrangement',
            self::HANDED_OVER         => 'Handed over to Attorneys',
            self::PAID                => 'Paid',
        };
    }

    /**
     * The next escalation step (used by the bulk "Send Notices" action).
     *
     * @return self
     */
    public function next(): self
    {
        return match ($this) {
            self::NONE, self::REMINDER => self::FIRST_NOTICE,
            self::FIRST_NOTICE         => self::SECOND_NOTICE,
            self::SECOND_NOTICE        => self::FINAL_NOTICE,
            self::FINAL_NOTICE         => self::LETTER_OF_DEMAND,
            default                    => $this,
        };
    }
}
