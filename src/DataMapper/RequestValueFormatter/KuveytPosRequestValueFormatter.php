<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

class KuveytPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }


    /**
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
     *
     * @param float $amount
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
        if ('CardExpireDateMonth' === $fieldName) {
            return $expDate->format('m');
        }

        if ('CardExpireDateYear' === $fieldName) {
            return $expDate->format('y');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        return $dateTime->format('Y-m-d\TH:i:s');
    }
}
