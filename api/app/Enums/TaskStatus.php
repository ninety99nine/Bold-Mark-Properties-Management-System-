<?php

namespace App\Enums;

enum TaskStatus: string
{
    case OPEN            = 'open';
    case IN_PROGRESS     = 'in_progress';
    case PENDING_PAYMENT = 'pending_payment';
    case ON_HOLD         = 'on_hold';
    case COMPLETE        = 'complete';

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
     * Human-friendly label.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN            => 'Open',
            self::IN_PROGRESS     => 'In Progress',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::ON_HOLD         => 'On Hold',
            self::COMPLETE        => 'Complete',
        };
    }
}
