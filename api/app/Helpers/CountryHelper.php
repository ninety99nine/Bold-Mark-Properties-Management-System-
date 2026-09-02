<?php

namespace App\Helpers;

class CountryHelper
{
    /**
     * Supported countries with their currency and formatting details.
     */
    private const COUNTRIES = [
        'ZA' => [
            'name'            => 'South Africa',
            'currency_code'   => 'ZAR',
            'currency_symbol' => 'R',
            'flag'            => "\u{1F1FF}\u{1F1E6}",
        ],
        'BW' => [
            'name'            => 'Botswana',
            'currency_code'   => 'BWP',
            'currency_symbol' => 'P',
            'flag'            => "\u{1F1E7}\u{1F1FC}",
        ],
        'NA' => [
            'name'            => 'Namibia',
            'currency_code'   => 'NAD',
            'currency_symbol' => 'N$',
            'flag'            => "\u{1F1F3}\u{1F1E6}",
        ],
        'US' => [
            'name'            => 'United States',
            'currency_code'   => 'USD',
            'currency_symbol' => '$',
            'flag'            => "\u{1F1FA}\u{1F1F8}",
        ],
        'GB' => [
            'name'            => 'United Kingdom',
            'currency_code'   => 'GBP',
            'currency_symbol' => "\u{00A3}",
            'flag'            => "\u{1F1EC}\u{1F1E7}",
        ],
    ];

    /**
     * Get all supported countries.
     *
     * @return array
     */
    public static function all(): array
    {
        return self::COUNTRIES;
    }

    /**
     * Get country details by code.
     *
     * @param string $countryCode
     * @return array|null
     */
    public static function get(string $countryCode): ?array
    {
        return self::COUNTRIES[strtoupper($countryCode)] ?? null;
    }

    /**
     * Get currency code for a country (e.g. 'ZA' -> 'ZAR').
     *
     * @param string $countryCode
     * @return string|null
     */
    public static function currencyCode(string $countryCode): ?string
    {
        return self::COUNTRIES[strtoupper($countryCode)]['currency_code'] ?? null;
    }

    /**
     * Get currency symbol for a country (e.g. 'ZA' -> 'R').
     *
     * @param string $countryCode
     * @return string|null
     */
    public static function currencySymbol(string $countryCode): ?string
    {
        return self::COUNTRIES[strtoupper($countryCode)]['currency_symbol'] ?? null;
    }

    /**
     * Get the list of supported countries for select dropdowns.
     *
     * @return array
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::COUNTRIES as $code => $details) {
            $options[] = [
                'code'            => $code,
                'name'            => $details['name'],
                'currency_code'   => $details['currency_code'],
                'currency_symbol' => $details['currency_symbol'],
                'flag'            => $details['flag'],
            ];
        }
        return $options;
    }
}
