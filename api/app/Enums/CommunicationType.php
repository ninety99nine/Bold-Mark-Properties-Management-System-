<?php

namespace App\Enums;

enum CommunicationType: string
{
    case MAIL = 'mail';
    case SMS  = 'sms';

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
     * Human-readable label for the Communicate → Send "Type" dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::MAIL => 'Mail',
            self::SMS  => 'SMS',
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
