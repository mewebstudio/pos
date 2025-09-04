<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;

class EstPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return EstV3Pos::class === $gatewayClass
            || EstPos::class === $gatewayClass;
    }

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
