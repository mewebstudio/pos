<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;

class InterPosRequestValueFormatter implements RequestValueFormatterInterface
{
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
     * @inheritDoc
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
        return $expDate->format('my');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName = null): string
    {
        throw new NotImplementedException();
    }
}
