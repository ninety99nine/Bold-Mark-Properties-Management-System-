<?php

namespace App\Helpers;

class BankHelper
{
    /**
     * South-African banks offered in the WeConnectU customer + debit-order forms.
     *
     * @var array<int, string>
     */
    private const BANKS = [
        'ABSA',
        'African Bank',
        'Bank Windhoek',
        'Bidvest Bank',
        'Capitec Bank',
        'Capitec Business',
        'Discovery Bank',
        'First National Bank',
        'Investec Private Bank',
        'Mercantile Bank',
        'Merchant Bank',
        'Nedbank',
        'Other',
        'PayFast',
        'Sasfin Bank Ltd',
        'Standard Bank',
        'Standard Chartered Bank',
        'Tyme Bank',
    ];

    /**
     * Bank-account types offered on the customer banking form.
     *
     * @var array<int, string>
     */
    private const ACCOUNT_TYPES = ['Current', 'Savings', 'Investment'];

    /**
     * All supported bank names.
     *
     * @return array<int, string>
     */
    public static function banks(): array
    {
        return self::BANKS;
    }

    /**
     * All supported account types.
     *
     * @return array<int, string>
     */
    public static function accountTypes(): array
    {
        return self::ACCOUNT_TYPES;
    }

    /**
     * Bank options as {value, label} pairs for API + frontend selects.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function bankOptions(): array
    {
        return array_map(fn (string $bank) => ['value' => $bank, 'label' => $bank], self::BANKS);
    }

    /**
     * Account-type options as {value, label} pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function accountTypeOptions(): array
    {
        return array_map(fn (string $type) => ['value' => $type, 'label' => $type], self::ACCOUNT_TYPES);
    }
}
