<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;

class GarantiPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'sales',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'preauth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'postauth',
        PosInterface::TX_TYPE_CANCEL         => 'void',
        PosInterface::TX_TYPE_REFUND         => 'refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'refund',
        PosInterface::TX_TYPE_ORDER_HISTORY  => 'orderhistoryinq',
        PosInterface::TX_TYPE_HISTORY        => 'orderlistinq',
        PosInterface::TX_TYPE_STATUS         => 'orderinq',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMappings = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE => '3D',
        PosInterface::MODEL_3D_PAY    => '3D_PAY',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }
}
