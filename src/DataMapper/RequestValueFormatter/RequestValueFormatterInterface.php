<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\PosInterface;

/**
 * Formats values according to API requirements
 */
interface RequestValueFormatterInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @param int<0, max> $installment
     *
     * @return string|int
     */
    public function formatInstallment(int $installment);


    /**
     * formats purchase amount
     *
     * @param float                        $amount
     * @param PosInterface::TX_TYPE_*|null $txType
     *
     * @return string|int|float
     */
    public function formatAmount(float $amount, string $txType = null);

    /**
     * @param \DateTimeInterface $expDate
     * @param string             $fieldName request expiration date/month/year field name
     *
     * @return string formatted expiration month, year, or month and year
     *
     * @throws \InvalidArgumentException when unsupported field name
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string;

    /**
     * @param \DateTimeInterface $dateTime
     * @param string             $fieldName request field name of the date
     *
     * @return string formatted date time
     *
     * @throws \InvalidArgumentException when unsupported field name
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName): string;
}
