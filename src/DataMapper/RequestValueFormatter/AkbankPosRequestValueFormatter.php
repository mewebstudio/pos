<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Gateways\AkbankPos;

class AkbankPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

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
     * @return string
     */
    public function formatAmount(float $amount, ?string $txType = null): string
    {
        return \number_format($amount, 2, '.', '');
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
    public function formatDateTime(\DateTimeInterface $dateTime, ?string $fieldName = null): string
    {
        return $dateTime->format('Y-m-d\TH:i:s').'.000';
    }
}
