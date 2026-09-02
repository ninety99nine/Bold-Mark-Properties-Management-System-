<?php

namespace App\Enums;

enum CommunityEntityType: string
{
    // Levy-billed ownership schemes (WeConnectU's entity types).
    case BODY_CORPORATE              = 'body_corporate';
    case HOME_OWNERS_ASSOCIATION     = 'home_owners_association';
    case PROPERTY_OWNERS_ASSOCIATION = 'property_owners_association';
    case FULL_TITLE                  = 'full_title';
    case COMPANY                     = 'company';

    // Rental portfolios (Bold Mark extension — beyond WeConnectU's scope).
    case RESIDENTIAL_RENTAL          = 'residential_rental';
    case COMMERCIAL_RENTAL           = 'commercial_rental';

    // Both levy and rent.
    case MIXED                       = 'mixed';

    /**
     * Human-readable label for display in the UI.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BODY_CORPORATE              => 'Body Corporate',
            self::HOME_OWNERS_ASSOCIATION     => 'Home Owners Association',
            self::PROPERTY_OWNERS_ASSOCIATION => 'Property Owners Association',
            self::FULL_TITLE                  => 'Full Title',
            self::COMPANY                     => 'Company',
            self::RESIDENTIAL_RENTAL          => 'Residential Rental',
            self::COMMERCIAL_RENTAL           => 'Commercial Rental',
            self::MIXED                       => 'Mixed',
        };
    }

    /**
     * The billing basis derived from the entity type — drives whether a
     * community is levy-billed, rent-billed, or both. This is the single source
     * of truth replacing the old separate "billing type" field.
     *
     * @return string  'levy' | 'rent' | 'mixed'
     */
    public function billingBasis(): string
    {
        return match ($this) {
            self::RESIDENTIAL_RENTAL, self::COMMERCIAL_RENTAL => 'rent',
            self::MIXED                                       => 'mixed',
            default                                           => 'levy',
        };
    }

    /**
     * Whether this entity type raises levies (admin / reserve / CSOS).
     *
     * @return bool
     */
    public function isLevyBilled(): bool
    {
        return in_array($this->billingBasis(), ['levy', 'mixed'], true);
    }

    /**
     * Whether this entity type raises rent.
     *
     * @return bool
     */
    public function isRentBilled(): bool
    {
        return in_array($this->billingBasis(), ['rent', 'mixed'], true);
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
