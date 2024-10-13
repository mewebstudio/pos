<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

class PayForPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     *
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }


    /**
     * 10.1 => "10.1"
     *
     * @inheritDoc
     */
    public function formatAmount(float $amount, ?string $txType = null): string
    {
        return (string) $amount;
    }

    /**
     * @inheritDoc
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        return $expDate->format('my');
    }

    /**
     * example 2024-04-14T16:45:30.000
     *
     * @inheritdoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        return $dateTime->format('Ymd');
    }
}
