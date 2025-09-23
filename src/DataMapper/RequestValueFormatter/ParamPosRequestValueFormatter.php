<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;

class ParamPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass;
    }

    /**
     * 0 => '1'
     * 1 => '1'
     * 2 => '2'
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '1';
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function formatAmount(float $amount, string $txType = null): string
    {
        $txTypes = [
           PosInterface::TX_TYPE_CANCEL,
           PosInterface::TX_TYPE_REFUND,
           PosInterface::TX_TYPE_REFUND_PARTIAL,
        ];

        if (\in_array($txType, $txTypes, true)) {
            return \number_format($amount, 2, '.', '');
        }

        return \number_format($amount, 2, ',', '');
    }

    /**
     * @inheritDoc
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        if ('KK_SK_Yil' === $fieldName) {
            return $expDate->format('Y');
        }

        if ('KK_SK_Ay' === $fieldName) {
            return $expDate->format('m');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * example 20.11.2018 15:00:00
     *
     * @inheritdoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        return $dateTime->format('d.m.Y H:i:s');
    }
}
