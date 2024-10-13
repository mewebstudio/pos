<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;

class EstPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * 0 => ''
     * 1 => ''
     * 2 => '2'
     *
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '';
    }


    /**
     * @inheritdoc
     *
     * @return string
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
        if ('Ecom_Payment_Card_ExpDate_Month' === $fieldName) {
            return $expDate->format('m');
        }

        if ('Ecom_Payment_Card_ExpDate_Year' === $fieldName) {
            return $expDate->format('y');
        }

        if ('Expires' === $fieldName) {
            return $expDate->format('m/y');
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
