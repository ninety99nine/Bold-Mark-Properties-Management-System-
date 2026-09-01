<?php

namespace App\Enums;

enum AgeingType: string
{
    case CALENDAR_MONTH = 'calendar_month';
    case DAYS           = 'days';

    /**
     * Human-readable label for display in the UI.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CALENDAR_MONTH => 'Calendar Month',
            self::DAYS           => 'Period Aging',
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
