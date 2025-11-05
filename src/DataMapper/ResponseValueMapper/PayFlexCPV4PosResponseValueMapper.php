<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\PayFlexCPV4Pos;

class PayFlexCPV4PosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass;
    }
}
