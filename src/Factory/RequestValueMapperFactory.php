<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\RequestValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\EstPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\InterPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayFlexV4PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PosNetRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PosNetV1PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\RequestValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\PosInterface;

/**
 * RequestValueMapperFactory
 */
class RequestValueMapperFactory
{
    /**
     * @var class-string<RequestValueMapperInterface>[]
     */
    private static array $requestValueMapperClasses = [
        ToslaPosRequestValueMapper::class,
        AkbankPosRequestValueMapper::class,
        EstPosRequestValueMapper::class,
        EstPosRequestValueMapper::class,
        GarantiPosRequestValueMapper::class,
        InterPosRequestValueMapper::class,
        KuveytPosRequestValueMapper::class,
        KuveytPosRequestValueMapper::class,
        VakifKatilimPosRequestValueMapper::class,
        PayForPosRequestValueMapper::class,
        PosNetRequestValueMapper::class,
        PosNetV1PosRequestValueMapper::class,
        ParamPosRequestValueMapper::class,
        PayFlexCPV4PosRequestValueMapper::class,
        PayFlexV4PosRequestValueMapper::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return RequestValueMapperInterface
     */
    public static function createForGateway(string $gatewayClass): RequestValueMapperInterface
    {

        /** @var class-string<RequestValueMapperInterface> $valueMapperClass */
        foreach (self::$requestValueMapperClasses as $valueMapperClass) {
            if ($valueMapperClass::supports($gatewayClass)) {
                return new $valueMapperClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
