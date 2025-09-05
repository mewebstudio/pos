<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\BoaPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\EstPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\InterPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexCPV4PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexV4PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayForPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PosNetResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\PosInterface;

/**
 * ResponseValueMapperFactory
 */
class ResponseValueMapperFactory
{
    /**
     * @var class-string<ResponseValueMapperInterface>[]
     */
    private static array $responseValueMapperClasses = [
        AkbankPosResponseValueMapper::class,
        BoaPosResponseValueMapper::class,
        EstPosResponseValueMapper::class,
        GarantiPosResponseValueMapper::class,
        InterPosResponseValueMapper::class,
        ParamPosResponseValueMapper::class,
        PayFlexCPV4PosResponseValueMapper::class,
        PayFlexV4PosResponseValueMapper::class,
        PayForPosResponseValueMapper::class,
        PosNetResponseValueMapper::class,
        PosNetV1PosResponseValueMapper::class,
        ToslaPosResponseValueMapper::class,
    ];

    /**
     * @param class-string<PosInterface>  $gatewayClass
     * @param RequestValueMapperInterface $requestValueMapper
     *
     * @return ResponseValueMapperInterface
     */
    public static function createForGateway(string $gatewayClass, RequestValueMapperInterface $requestValueMapper): ResponseValueMapperInterface
    {
        /** @var class-string<ResponseValueMapperInterface> $valueMapperClass */
        foreach (self::$responseValueMapperClasses as $valueMapperClass) {
            if (!$valueMapperClass::supports($gatewayClass)) {
                continue;
            }

            $secureTypeMappings = [];
            $txTypeMappings     = [];
            $currencyMappings   = [];

            if (self::areTxMappingsRequired($valueMapperClass)) {
                $txTypeMappings = $requestValueMapper->getTxTypeMappings();
            }

            if (self::areSecurityTypeMappingsRequired($valueMapperClass)) {
                $secureTypeMappings = $requestValueMapper->getSecureTypeMappings();
            }

            if (self::areCurrencyMappingsRequired($valueMapperClass)) {
                $currencyMappings = $requestValueMapper->getCurrencyMappings();
            }

            return new $valueMapperClass(
                $currencyMappings,
                $txTypeMappings,
                $secureTypeMappings,
            );
        }

        throw new DomainException('unsupported gateway');
    }

    private static function areTxMappingsRequired(string $valueMapperClass): bool
    {
        return \in_array($valueMapperClass, [
            AkbankPosResponseValueMapper::class,
            GarantiPosResponseValueMapper::class,
            BoaPosResponseValueMapper::class,
            PayFlexCPV4PosResponseValueMapper::class,
            PayFlexV4PosResponseValueMapper::class,
            PayForPosResponseValueMapper::class,
            PosNetResponseValueMapper::class,
            PosNetV1PosResponseValueMapper::class,
            ToslaPosResponseValueMapper::class,
        ], true);
    }

    private static function areCurrencyMappingsRequired(string $valueMapperClass): bool
    {
        return \in_array($valueMapperClass, [
            AkbankPosResponseValueMapper::class,
            BoaPosResponseValueMapper::class,
            EstPosResponseValueMapper::class,
            GarantiPosResponseValueMapper::class,
            InterPosResponseValueMapper::class,
            PayFlexCPV4PosResponseValueMapper::class,
            PayFlexV4PosResponseValueMapper::class,
            PayForPosResponseValueMapper::class,
            PosNetResponseValueMapper::class,
            PosNetV1PosResponseValueMapper::class,
            ToslaPosResponseValueMapper::class,
        ], true);
    }

    private static function areSecurityTypeMappingsRequired(string $valueMapperClass): bool
    {
        return \in_array($valueMapperClass, [
            EstPosResponseValueMapper::class,
            GarantiPosResponseValueMapper::class,
            BoaPosResponseValueMapper::class,
            PayForPosResponseValueMapper::class,
        ], true);
    }
}
