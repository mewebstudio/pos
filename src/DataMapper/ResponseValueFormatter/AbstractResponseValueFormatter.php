<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

abstract class AbstractResponseValueFormatter implements ResponseValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        $inst = (int) $installment;

        return $inst > 1 ? $inst : 0;
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return (float) $amount;
    }

    /**
     * @inheritdoc
     */
    public function formatDateTime(string $dateTime, string $txType): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime);
    }
}
