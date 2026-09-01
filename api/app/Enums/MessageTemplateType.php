<?php

namespace App\Enums;

enum MessageTemplateType: string
{
    case EMAIL = 'email';
    case SMS   = 'sms';

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
     * Human-readable label for the message-template list.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS   => 'SMS',
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
