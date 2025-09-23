<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\RequestValueFormatter\AkbankPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\EstPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\InterPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\RequestValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\VakifKatilimPosRequestValueFormatter;
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
 * RequestValueFormatterFactory
 */
class RequestValueFormatterFactory
{
    /**
     * @param class-string $gatewayClass
     *
     * @return RequestValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): RequestValueFormatterInterface
    {
        $classMappings = [
            ToslaPos::class         => ToslaPosRequestValueFormatter::class,
            AkbankPos::class        => AkbankPosRequestValueFormatter::class,
            EstPos::class           => EstPosRequestValueFormatter::class,
            EstV3Pos::class         => EstPosRequestValueFormatter::class,
            GarantiPos::class       => GarantiPosRequestValueFormatter::class,
            InterPos::class         => InterPosRequestValueFormatter::class,
            KuveytPos::class        => KuveytPosRequestValueFormatter::class,
            KuveytSoapApiPos::class => KuveytPosRequestValueFormatter::class,
            VakifKatilimPos::class  => VakifKatilimPosRequestValueFormatter::class,
            ParamPos::class         => ParamPosRequestValueFormatter::class,
            PayForPos::class        => PayForPosRequestValueFormatter::class,
            PosNet::class           => PosNetRequestValueFormatter::class,
            PosNetV1Pos::class      => PosNetV1PosRequestValueFormatter::class,
            PayFlexCPV4Pos::class   => PayFlexCPV4PosRequestValueFormatter::class,
            PayFlexV4Pos::class     => PayFlexV4PosRequestValueFormatter::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass]();
        }

        throw new DomainException('unsupported gateway');
    }
}
