<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;

class InterPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        throw new NotImplementedException();
    }

    /**
     * 0 => 0.0
     * 1.056,2 => 1056.2
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return (float) \str_replace(',', '.', \str_replace('.', '', (string) $amount));
    }
}
