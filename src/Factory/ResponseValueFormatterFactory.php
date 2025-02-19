<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\ResponseValueFormatter\BasicResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\EstPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\ParamPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\PosNetResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueFormatter\ToslaPosResponseValueFormatter;
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

/**
 * ResponseValueFormatterFactory
 */
class ResponseValueFormatterFactory
{
    /**
     * @param class-string $gatewayClass
     *
     * @return ResponseValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): ResponseValueFormatterInterface
    {
        $classMappings = [
            AkbankPos::class       => BasicResponseValueFormatter::class,
            EstPos::class          => EstPosResponseValueFormatter::class,
            EstV3Pos::class        => EstPosResponseValueFormatter::class,
            GarantiPos::class      => GarantiPosResponseValueFormatter::class,
            InterPos::class        => InterPosResponseValueFormatter::class,
            KuveytPos::class       => BoaPosResponseValueFormatter::class,
            ParamPos::class        => ParamPosResponseValueFormatter::class,
            PayFlexCPV4Pos::class  => BasicResponseValueFormatter::class,
            PayFlexV4Pos::class    => BasicResponseValueFormatter::class,
            PayForPos::class       => BasicResponseValueFormatter::class,
            PosNet::class          => PosNetResponseValueFormatter::class,
            PosNetV1Pos::class     => PosNetResponseValueFormatter::class,
            ToslaPos::class        => ToslaPosResponseValueFormatter::class,
            VakifKatilimPos::class => BoaPosResponseValueFormatter::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass]();
        }

        throw new DomainException('unsupported gateway');
    }
}
