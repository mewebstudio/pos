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
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * RequestDataMapperFactory
 */
class RequestDataMapperFactory
{
    /**
     * @param class-string                            $gatewayClass
     * @param EventDispatcherInterface                $eventDispatcher
     * @param CryptInterface                          $crypt
     * @param array<PosInterface::CURRENCY_*, string> $currencies
     *
     * @return RequestDataMapperInterface
     */
    public static function createGatewayRequestMapper(string $gatewayClass, EventDispatcherInterface $eventDispatcher, CryptInterface $crypt, array $currencies = []): RequestDataMapperInterface
    {
        $classMappings = [
            AkbankPos::class       => AkbankPosRequestDataMapper::class,
            EstPos::class          => EstPosRequestDataMapper::class,
            EstV3Pos::class        => EstV3PosRequestDataMapper::class,
            GarantiPos::class      => GarantiPosRequestDataMapper::class,
            InterPos::class        => InterPosRequestDataMapper::class,
            KuveytPos::class       => KuveytPosRequestDataMapper::class,
            ParamPos::class        => ParamPosRequestDataMapper::class,
            PayFlexCPV4Pos::class  => PayFlexCPV4PosRequestDataMapper::class,
            PayFlexV4Pos::class    => PayFlexV4PosRequestDataMapper::class,
            PayForPos::class       => PayForPosRequestDataMapper::class,
            PosNet::class          => PosNetRequestDataMapper::class,
            PosNetV1Pos::class     => PosNetV1PosRequestDataMapper::class,
            ToslaPos::class        => ToslaPosRequestDataMapper::class,
            VakifKatilimPos::class => VakifKatilimPosRequestDataMapper::class,
        ];
        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass]($eventDispatcher, $crypt, $currencies);
        }

        throw new DomainException(\sprintf('Request data mapper not found for the gateway %s', $gatewayClass));
    }
}
