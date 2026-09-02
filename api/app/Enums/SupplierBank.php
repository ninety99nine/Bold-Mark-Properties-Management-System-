<?php

namespace App\Enums;

/**
 * The bank a supplier's account is held at, mirroring WeConnectU's Bank
 * dropdown. Unlike the other supplier enums, the backed value IS the human
 * label — suppliers store bank_name as this string. label() returns the same
 * string so the shared options() shape stays consistent.
 */
enum SupplierBank: string
{
    case ABSA                     = 'ABSA';
    case AFRICAN_BANK             = 'African Bank';
    case BANK_WINDHOEK            = 'Bank Windhoek';
    case BIDVEST_BANK             = 'Bidvest Bank';
    case CAPITEC_BANK             = 'Capitec Bank';
    case CAPITEC_BUSINESS         = 'Capitec Business';
    case DISCOVERY_BANK           = 'Discovery Bank';
    case FIRST_NATIONAL_BANK      = 'First National Bank';
    case INVESTEC_PRIVATE_BANK    = 'Investec Private Bank';
    case MERCANTILE_BANK          = 'Mercantile Bank';
    case MERCHANT_BANK            = 'Merchant Bank';
    case NEDBANK                  = 'Nedbank';
    case OTHER                    = 'Other';
    case PAYFAST                  = 'PayFast';
    case SASFIN_BANK              = 'Sasfin Bank Ltd';
    case STANDARD_BANK            = 'Standard Bank';
    case STANDARD_CHARTERED_BANK  = 'Standard Chartered Bank';
    case TYME_BANK               = 'Tyme Bank';

    /**
     * The exact human label shown in the UI dropdown (equals the backed value).
     *
     * @return string
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Return all enum values as a plain array (used in migrations / matching).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return [value => label] option pairs for the UI dropdown, in display order.
     *
     * @return array<array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
