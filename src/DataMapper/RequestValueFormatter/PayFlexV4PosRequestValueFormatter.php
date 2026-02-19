<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Gateways\PayFlexV4Pos;

class PayFlexV4PosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }

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
     * ex: 10.1 => 10.10
     *
     * @inheritDoc
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
        if ('ExpiryDate' === $fieldName) {
            return $expDate->format('ym');
        }

        if ('Expiry' === $fieldName) {
            return $expDate->format('Ym');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * example: 20240414
     *
     * @inheritdoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, ?string $fieldName = null, ?string $txType = null): string
    {
        return $dateTime->format('Ymd');
    }
}
