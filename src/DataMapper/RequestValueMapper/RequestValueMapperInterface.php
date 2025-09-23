<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Maps order/request values to values that are expected by the POS API.
 */
interface RequestValueMapperInterface
{
    /**
     * @return array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>>
     */
    public function getTxTypeMappings(): array;

    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     * @param array<string, mixed>    $order
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException
     */
    public function mapTxType(string $txType, ?string $paymentModel = null, ?array $order = []): string;

    /**
     * @param PosInterface::MODEL_* $paymentModel
     *
     * @return string
     */
    public function mapSecureType(string $paymentModel): string;

    /**
     * @return array<PosInterface::MODEL_*, string>
     */
    public function getSecureTypeMappings(): array;

    /**
     * @phpstan-param PosInterface::CURRENCY_* $currency
     *
     * @param string $currency
     *
     * @return string|int
     */
    public function mapCurrency(string $currency);

    /**
     * @return non-empty-array<PosInterface::CURRENCY_*, string|int>
     */
    public function getCurrencyMappings(): array;

    /**
     * If language mapping is not found, returns maps for default language (PosInterface::LANG_TR) or $lang itself.
     *
     * @param PosInterface::LANG_* $lang
     *
     * @return string
     */
    public function mapLang(string $lang): string;

    /**
     * @return array<PosInterface::LANG_*, string>
     */
    public function getLangMappings(): array;

    /**
     * @return array<'DAY'|'WEEK'|'MONTH'|'YEAR', string>
     */
    public function getRecurringOrderFrequencyMappings(): array;

    /**
     * @param string $period
     *
     * @return string
     */
    public function mapRecurringFrequency(string $period): string;

    /**
     * @return array<CreditCardInterface::CARD_TYPE_*, string>
     */
    public function getCardTypeMappings(): array;

    /**
     * @param CreditCardInterface::CARD_TYPE_* $cardType
     *
     * @return string
     */
    public function mapCardType(string $cardType): string;
}
