<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Gateways\VakifKatilimPos;

class VakifKatilimPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
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
     * example: 100 to 10000, or 10.01 to 1001
     *
     * @inheritDoc
     */
    public function formatAmount(float $amount, ?string $txType = null): int
    {
        return (int) (\round($amount, 2) * 100);
    }

    /**
     * @inheritDoc
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        if ('CardExpireDateYear' === $fieldName) {
            return $expDate->format('y');
        }

        if ('CardExpireDateMonth' === $fieldName) {
            return $expDate->format('m');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        if ('StartDate' === $fieldName || 'EndDate' === $fieldName) {
            return $dateTime->format('Y-m-d');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }
}
