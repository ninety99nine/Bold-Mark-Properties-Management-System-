<?php

namespace App\Enums;

/**
 * VAT treatment options offered on an allocation / allocation rule, mirroring
 * WeConnectU's VAT dropdown. The backed value is a stable slug; label() returns
 * the exact human string shown in the UI.
 */
enum VatType: string
{
    case EXEMPT                    = 'exempt';
    case NOT_APPLICABLE            = 'not_applicable';
    case NORMAL_15                 = 'normal_15';
    case INPUT_15                  = 'input_15';
    case OUTPUT_15                 = 'output_15';
    case OUTPUT_15_5               = 'output_15_5';
    case NON_SUPPLIES_0            = 'non_supplies_0';
    case INPUT_15_5                = 'input_15_5';
    case NORMAL_15_5               = 'normal_15_5';
    case ZERO_RATED_0             = 'zero_rated_0';
    case BAD_DEBT_15_5             = 'bad_debt_15_5';
    case BAD_DEBT_15               = 'bad_debt_15';
    case CAPITAL_GOODS_15          = 'capital_goods_15';
    case CAPITAL_GOODS_15_5        = 'capital_goods_15_5';
    case CORRECTION_100            = 'correction_100';
    case ACCOMMODATION_28          = 'accommodation_28';
    case ACCOMMODATION_28_NEW      = 'accommodation_28_new';

    /**
     * The exact human label shown in the UI dropdown.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::EXEMPT               => 'VAT Exempt',
            self::NOT_APPLICABLE       => 'VAT Not Applicable',
            self::NORMAL_15            => 'VAT Normal 15 %',
            self::INPUT_15             => 'VAT Input 15 %',
            self::OUTPUT_15            => 'VAT Output 15 %',
            self::OUTPUT_15_5          => 'VAT Output 15.5 %',
            self::NON_SUPPLIES_0       => 'VAT Non-Supplies 0 %',
            self::INPUT_15_5           => 'VAT Input 15.5 %',
            self::NORMAL_15_5          => 'VAT Normal 15.5 %',
            self::ZERO_RATED_0         => 'VAT Zero Rated 0 % (Non Exports)',
            self::BAD_DEBT_15_5        => 'VAT Bad Debt 15.5 %',
            self::BAD_DEBT_15          => 'VAT Bad Debt 15 %',
            self::CAPITAL_GOODS_15     => 'VAT Capital Goods 15 %',
            self::CAPITAL_GOODS_15_5   => 'VAT Capital Goods 15.5 %',
            self::CORRECTION_100       => 'VAT Correction 100 %',
            self::ACCOMMODATION_28     => 'VAT Accomodation Exceeding 28 Days',
            self::ACCOMMODATION_28_NEW => 'VAT Accomodation Exceeding 28 Days (new)',
        };
    }

    /**
     * The numeric VAT percentage for this treatment (0 when no VAT applies).
     *
     * @return float
     */
    public function rate(): float
    {
        return match ($this) {
            self::EXEMPT, self::NOT_APPLICABLE, self::NON_SUPPLIES_0, self::ZERO_RATED_0 => 0.0,
            self::NORMAL_15, self::INPUT_15, self::OUTPUT_15, self::BAD_DEBT_15, self::CAPITAL_GOODS_15 => 15.0,
            self::NORMAL_15_5, self::INPUT_15_5, self::OUTPUT_15_5, self::BAD_DEBT_15_5, self::CAPITAL_GOODS_15_5 => 15.5,
            self::CORRECTION_100 => 100.0,
            self::ACCOMMODATION_28, self::ACCOMMODATION_28_NEW => 28.0,
        };
    }

    /**
     * Input VAT (recoverable, on expenses/supplier documents).
     *
     * @return bool
     */
    public function isInput(): bool
    {
        return in_array($this, [self::INPUT_15, self::INPUT_15_5, self::CAPITAL_GOODS_15, self::CAPITAL_GOODS_15_5], true);
    }

    /**
     * Output VAT (payable, on income/customer documents).
     *
     * @return bool
     */
    public function isOutput(): bool
    {
        return in_array($this, [self::OUTPUT_15, self::OUTPUT_15_5, self::BAD_DEBT_15, self::BAD_DEBT_15_5], true);
    }

    /**
     * Which side of the VAT Control account this treatment posts to:
     * 'input' | 'output' | null (null = generic/normal — the caller decides by
     * document context, e.g. output on a customer invoice, input on a supplier bill).
     *
     * @return string|null
     */
    public function controlSide(): ?string
    {
        if ($this->isInput()) {
            return 'input';
        }
        if ($this->isOutput()) {
            return 'output';
        }
        return null;
    }

    /**
     * Whether this treatment attracts VAT at all.
     *
     * @return bool
     */
    public function hasVat(): bool
    {
        return $this->rate() > 0.0
            && ! in_array($this, [self::EXEMPT, self::NOT_APPLICABLE], true);
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
