<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\PosInterface;

class GarantiPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        if (null === $installment) {
            return 0;
        }

        if (PosInterface::TX_TYPE_HISTORY === $txType) {
            // history response
            if ('Pesin' === $installment || '1' === $installment) {
                return 0;
            }
        }

        return parent::formatInstallment($installment, $txType);
    }

    /**
     * 100001 => 1000.01
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return ((float) $amount) / 100;
    }
}
