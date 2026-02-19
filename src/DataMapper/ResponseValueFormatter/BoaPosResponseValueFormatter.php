<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;

/**
 * Boa Pos is used by Kuveyt and VakifKatilim
 */
class BoaPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass
            || KuveytSoapApiPos::class === $gatewayClass
            || VakifKatilimPos::class === $gatewayClass;
    }

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
