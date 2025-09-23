<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\PosInterface;

/**
 * Formats values according to API requirements
 */
interface ResponseValueFormatterInterface
{
    /**
     * @param string|null             $installment
     * @param PosInterface::TX_TYPE_* $txType transaction type of the API request
     *
     * @return int<2, max>|0
     */
    public function formatInstallment(?string $installment, string $txType): int;


    /**
     * formats purchase amount
     *
     * @param string|float            $amount
     * @param PosInterface::TX_TYPE_* $txType transaction type of the API request
     *
     * @return float
     */
    public function formatAmount($amount, string $txType): float;

    /**
     * @param string                  $dateTime
     * @param PosInterface::TX_TYPE_* $txType transaction type of the API request
     *
     * @return \DateTimeImmutable formatted date time
     */
    public function formatDateTime(string $dateTime, string $txType): \DateTimeImmutable;
}
