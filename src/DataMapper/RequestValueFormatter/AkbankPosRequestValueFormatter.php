<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper\RequestValueFormatter;

class AkbankPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * 0 => 1
     * 1 => 1
     * 2 => 2
     * @inheritDoc
     */
    public function formatInstallment(int $installment): int
    {
        return \max($installment, 1);
    }

    /**
     * @param float $amount
     *
     * @return float
     */
    public function formatAmount(float $amount): float
    {
        return $amount;
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
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null, string $txType = null): string
    {
        return $dateTime->format('Y-m-d\TH:i:s').'.000';
    }
}
