<?php

namespace App\Enums;

enum RecipientGroup: string
{
    case OWNERS           = 'owners';
    case DIRECTORS        = 'directors';
    case COMPLEX_MANAGERS = 'complex_managers';
    case RENTAL_AGENTS    = 'rental_agents';
    case ATTORNEYS        = 'attorneys';
    case OCCUPANTS        = 'occupants';
    case BONDHOLDERS      = 'bondholders';

    /**
     * Return all enum values as a plain array (used in migrations + validation).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label matching the WeConnectU "Send To" checkboxes.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNERS           => 'Owners',
            self::DIRECTORS        => 'Directors/Trustees',
            self::COMPLEX_MANAGERS => 'Complex Manager/s',
            self::RENTAL_AGENTS    => 'Rental Agents',
            self::ATTORNEYS        => 'Attorneys / Additional',
            self::OCCUPANTS        => 'Occupants',
            self::BONDHOLDERS      => 'Bondholders',
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
