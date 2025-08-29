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
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;

/**
 * ResponseValueMapperFactory
 */
class ResponseValueMapperFactory
{
    /**
     * @param class-string                $gatewayClass
     * @param RequestValueMapperInterface $requestValueMapper
     *
     * @return ResponseValueMapperInterface
     */
    public static function createForGateway(string $gatewayClass, RequestValueMapperInterface $requestValueMapper): ResponseValueMapperInterface
    {
        $classMappings = [
            AkbankPos::class        => AkbankPosResponseValueMapper::class,
            EstPos::class           => EstPosResponseValueMapper::class,
            EstV3Pos::class         => EstPosResponseValueMapper::class,
            GarantiPos::class       => GarantiPosResponseValueMapper::class,
            InterPos::class         => InterPosResponseValueMapper::class,
            KuveytPos::class        => BoaPosResponseValueMapper::class,
            KuveytSoapApiPos::class => BoaPosResponseValueMapper::class,
            ParamPos::class         => ParamPosResponseValueMapper::class,
            PayFlexCPV4Pos::class   => PayFlexCPV4PosResponseValueMapper::class,
            PayFlexV4Pos::class     => PayFlexV4PosResponseValueMapper::class,
            PayForPos::class        => PayForPosResponseValueMapper::class,
            PosNet::class           => PosNetResponseValueMapper::class,
            PosNetV1Pos::class      => PosNetV1PosResponseValueMapper::class,
            ToslaPos::class         => ToslaPosResponseValueMapper::class,
            VakifKatilimPos::class  => BoaPosResponseValueMapper::class,
        ];

        if (!isset($classMappings[$gatewayClass])) {
            throw new DomainException('unsupported gateway');
        }

        $secureTypeMappings = [];
        $txTypeMappings     = [];
        $currencyMappings   = [];

        if (\in_array($classMappings[$gatewayClass], [
            AkbankPosResponseValueMapper::class,
            GarantiPosResponseValueMapper::class,
            BoaPosResponseValueMapper::class,
            PayFlexCPV4PosResponseValueMapper::class,
            PayFlexV4PosResponseValueMapper::class,
            PayForPosResponseValueMapper::class,
            PosNetResponseValueMapper::class,
            PosNetV1PosResponseValueMapper::class,
            ToslaPosResponseValueMapper::class,
        ])) {
            $txTypeMappings = $requestValueMapper->getTxTypeMappings();
        }

        if (\in_array($classMappings[$gatewayClass], [
            EstPosResponseValueMapper::class,
            GarantiPosResponseValueMapper::class,
            BoaPosResponseValueMapper::class,
            PayForPosResponseValueMapper::class,
        ], true)) {
            $secureTypeMappings = $requestValueMapper->getSecureTypeMappings();
        }

        if (\in_array($classMappings[$gatewayClass], [
            AkbankPos::class        => AkbankPosResponseValueMapper::class,
            EstPos::class           => EstPosResponseValueMapper::class,
            EstV3Pos::class         => EstPosResponseValueMapper::class,
            GarantiPos::class       => GarantiPosResponseValueMapper::class,
            InterPos::class         => InterPosResponseValueMapper::class,
            KuveytPos::class        => BoaPosResponseValueMapper::class,
            KuveytSoapApiPos::class => BoaPosResponseValueMapper::class,
            PayFlexCPV4Pos::class   => PayFlexCPV4PosResponseValueMapper::class,
            PayFlexV4Pos::class     => PayFlexV4PosResponseValueMapper::class,
            PayForPos::class        => PayForPosResponseValueMapper::class,
            PosNet::class           => PosNetResponseValueMapper::class,
            PosNetV1Pos::class      => PosNetV1PosResponseValueMapper::class,
            ToslaPos::class         => ToslaPosResponseValueMapper::class,
            VakifKatilimPos::class  => BoaPosResponseValueMapper::class,
        ], true)) {
            $currencyMappings = $requestValueMapper->getCurrencyMappings();
        }

        return new $classMappings[$gatewayClass](
            $currencyMappings,
            $txTypeMappings,
            $secureTypeMappings,
        );
    }
}
