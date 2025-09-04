<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytSoapApiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

/**
 * ResponseDataMapperFactory
 */
class ResponseDataMapperFactory
{
    /**
     * @var class-string<ResponseDataMapperInterface>[]
     */
    private static array $responseDataMapperClasses = [
        AkbankPosResponseDataMapper::class,
        EstPosResponseDataMapper::class,
        GarantiPosResponseDataMapper::class,
        InterPosResponseDataMapper::class,
        KuveytPosResponseDataMapper::class,
        KuveytSoapApiPosResponseDataMapper::class,
        ParamPosResponseDataMapper::class,
        PayFlexCPV4PosResponseDataMapper::class,
        PayFlexV4PosResponseDataMapper::class,
        PayForPosResponseDataMapper::class,
        PosNetResponseDataMapper::class,
        PosNetV1PosResponseDataMapper::class,
        ToslaPosResponseDataMapper::class,
        VakifKatilimPosResponseDataMapper::class,
    ];

    /**
     * @param class-string<PosInterface>      $gatewayClass
     * @param ResponseValueFormatterInterface $valueFormatter
     * @param ResponseValueMapperInterface    $valueMapper
     * @param LoggerInterface                 $logger
     *
     * @return ResponseDataMapperInterface
     */
    public static function createGatewayResponseMapper(
        string                          $gatewayClass,
        ResponseValueFormatterInterface $valueFormatter,
        ResponseValueMapperInterface    $valueMapper,
        LoggerInterface                 $logger
    ): ResponseDataMapperInterface {

        /** @var class-string<ResponseDataMapperInterface> $responseDataMapperClass */
        foreach (self::$responseDataMapperClasses as $responseDataMapperClass) {
            if ($responseDataMapperClass::supports($gatewayClass)) {
                return new $responseDataMapperClass(
                    $valueFormatter,
                    $valueMapper,
                    $logger
                );
            }
        }

        throw new DomainException(\sprintf('Response data mapper not found for the gateway %s', $gatewayClass));
    }
}
