<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\PayForPos;

class PayForPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }
}
