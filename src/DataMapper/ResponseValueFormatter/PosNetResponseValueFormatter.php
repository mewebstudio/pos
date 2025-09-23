<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\PosInterface;

/**
 * Value formatter for PosNet and PosNetV1Pos
 */
class PosNetResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (PosInterface::TX_TYPE_STATUS === $txType) {
            // "1,16" => 1.16
            return (float) \str_replace(',', '.', \str_replace('.', '', (string) $amount));
        }

        return ((int) $amount) / 100;
    }
}
