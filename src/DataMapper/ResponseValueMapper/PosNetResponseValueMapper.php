<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\PosNet;

class PosNetResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNet::class === $gatewayClass;
    }
}
