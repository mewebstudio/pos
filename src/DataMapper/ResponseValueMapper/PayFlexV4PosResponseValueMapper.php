<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;

class PayFlexV4PosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @inheritdocs
     */
    protected array $secureTypeMappings = [
        '1' => PosInterface::MODEL_NON_SECURE,
        '2' => PosInterface::MODEL_3D_SECURE,
        '3' => PosInterface::MODEL_3D_PAY,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }
}
