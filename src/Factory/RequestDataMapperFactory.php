<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * RequestDataMapperFactory
 */
class RequestDataMapperFactory
{
    /**
     * @var class-string<RequestDataMapperInterface>[]
     */
    private static array $requestDataMapperClasses = [
        AkbankPosRequestDataMapper::class,
        EstPosRequestDataMapper::class,
        EstV3PosRequestDataMapper::class,
        GarantiPosRequestDataMapper::class,
        InterPosRequestDataMapper::class,
        KuveytPosRequestDataMapper::class,
        KuveytSoapApiPosRequestDataMapper::class,
        ParamPosRequestDataMapper::class,
        Param3DHostPosRequestDataMapper::class,
        PayFlexCPV4PosRequestDataMapper::class,
        PayFlexV4PosRequestDataMapper::class,
        PayForPosRequestDataMapper::class,
        PosNetRequestDataMapper::class,
        PosNetV1PosRequestDataMapper::class,
        ToslaPosRequestDataMapper::class,
        VakifKatilimPosRequestDataMapper::class,
    ];

    /**
     * @param class-string<PosInterface>     $gatewayClass
     * @param RequestValueMapperInterface    $valueMapper
     * @param RequestValueFormatterInterface $valueFormatter
     * @param EventDispatcherInterface       $eventDispatcher
     * @param CryptInterface                 $crypt
     *
     * @return RequestDataMapperInterface
     */
    public static function createGatewayRequestMapper(
        string                         $gatewayClass,
        RequestValueMapperInterface    $valueMapper,
        RequestValueFormatterInterface $valueFormatter,
        EventDispatcherInterface       $eventDispatcher,
        CryptInterface                 $crypt
    ): RequestDataMapperInterface {
        /** @var class-string<RequestDataMapperInterface> $requestDataMapperClass */
        foreach (self::$requestDataMapperClasses as $requestDataMapperClass) {
            if ($requestDataMapperClass::supports($gatewayClass)) {
                return new $requestDataMapperClass($valueMapper, $valueFormatter, $eventDispatcher, $crypt);
            }
        }


        throw new DomainException(\sprintf('Request data mapper not found for the gateway %s', $gatewayClass));
    }
}
