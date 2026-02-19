<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;

class VakifKatilimPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * Currency mapping
     *
     * {@inheritdoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '0949',
        PosInterface::CURRENCY_USD => '0840',
        PosInterface::CURRENCY_EUR => '0978',
        PosInterface::CURRENCY_GBP => '0826',
        PosInterface::CURRENCY_JPY => '0392',
        PosInterface::CURRENCY_RUB => '0810',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3',
        PosInterface::MODEL_NON_SECURE => '5',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
    }
}
