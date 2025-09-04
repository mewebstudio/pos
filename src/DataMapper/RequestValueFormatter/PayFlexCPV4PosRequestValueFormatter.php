<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\PayFlexCPV4Pos;

class PayFlexCPV4PosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass;
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
        if ('ExpireMonth' === $fieldName) {
            return $expDate->format('m');
        }

        if ('ExpireYear' === $fieldName) {
            return $expDate->format('y');
        }

        if ('Expiry' === $fieldName) {
            return $expDate->format('Ym');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        throw new NotImplementedException();
    }
}
