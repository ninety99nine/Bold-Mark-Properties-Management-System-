<?php

namespace App\Enums;

enum CommunityStatus: string
{
    // Live communities being actively managed (billing, occupants, etc.).
    case ACTIVE    = 'active';

    // Onboarding — the community is being taken on (data capture / setup) and is
    // not yet live. Mirrors WeConnectU's "Take-on" lifecycle stage.
    case TAKE_ON   = 'take_on';

    // Temporarily paused / off-boarded — retained for history but not managed.
    case SUSPENDED = 'suspended';

    /**
     * Human-readable label for display in the UI.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE    => 'Active',
            self::TAKE_ON   => 'Take-on',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * All enum values as a plain array (for validation / migrations).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
