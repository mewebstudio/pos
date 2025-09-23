<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Gateways\GarantiPos;

class GarantiPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * 0 => ''
     * 1 => ''
     * 2 => '2'
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '';
    }


    /**
     * converts 100 to 10000, or 10.01 to 1001
     *
     * @inheritDoc
     *
     * @return int
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
        if ('cardexpiredatemonth' === $fieldName) {
            return $expDate->format('m');
        }

        if ('cardexpiredateyear' === $fieldName) {
            return $expDate->format('y');
        }

        if ('ExpireDate' === $fieldName) {
            return $expDate->format('my');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        return $dateTime->format('d/m/Y H:i');
    }
}
