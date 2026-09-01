<?php

namespace App\Enums;

enum CommunityMemberType: string
{
    case OWNER            = 'owner';
    case COMPLEX_MANAGER  = 'complex_manager';
    case DIRECTOR_TRUSTEE = 'director_trustee';

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
     * Human-friendly label for the base user type.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER            => 'Owner',
            self::COMPLEX_MANAGER  => 'Complex Manager',
            self::DIRECTOR_TRUSTEE => 'Director/Trustee',
        };
    }
}
