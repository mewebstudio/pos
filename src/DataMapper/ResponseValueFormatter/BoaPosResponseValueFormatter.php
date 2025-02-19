<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\PosInterface;

/**
 * Boa Pos is used by Kuveyt and VakifKatilim
 */
class BoaPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (\in_array($txType, [PosInterface::TX_TYPE_STATUS, PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::TX_TYPE_HISTORY], true)) {
            return parent::formatAmount($amount, $txType);
        }

        return (float) $amount / 100;
    }
}
