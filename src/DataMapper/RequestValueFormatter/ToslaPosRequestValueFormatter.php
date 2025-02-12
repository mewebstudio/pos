<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

class ToslaPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     *
     * @inheritDoc
     */
    public function formatInstallment(int $installment): int
    {
        return $installment > 1 ? $installment : 0;
    }


    /**
     * formats 10.01 to 1001
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
        if ('ExpireDate' === $fieldName) {
            return $expDate->format('m/y');
        }

        if ('expireDate' === $fieldName) {
            return $expDate->format('my');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        if ('timeSpan' === $fieldName) {
            return $dateTime->format('YmdHis');
        }

        if ('transactionDate' === $fieldName) {
            return $dateTime->format('Ymd');
        }

        throw new \InvalidArgumentException('Unsupported field name');
    }
}
