<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\PosInterface;

class EstPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (PosInterface::TX_TYPE_STATUS === $txType || PosInterface::TX_TYPE_ORDER_HISTORY === $txType) {
            return (float) $amount / 100;
        }

        return (float) $amount;
    }
}
