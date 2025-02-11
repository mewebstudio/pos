<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Serializer\AkbankPosSerializer;
use Mews\Pos\Serializer\EstPosSerializer;
use Mews\Pos\Serializer\GarantiPosSerializer;
use Mews\Pos\Serializer\InterPosSerializer;
use Mews\Pos\Serializer\KuveytPosSerializer;
use Mews\Pos\Serializer\ParamPosSerializer;
use Mews\Pos\Serializer\PayFlexCPV4PosSerializer;
use Mews\Pos\Serializer\PayFlexV4PosSerializer;
use Mews\Pos\Serializer\PayForPosSerializer;
use Mews\Pos\Serializer\PosNetSerializer;
use Mews\Pos\Serializer\PosNetV1PosSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Serializer\ToslaPosSerializer;
use Mews\Pos\Serializer\VakifKatilimPosSerializer;

/**
 * SerializerFactory
 */
class SerializerFactory
{
    /**
     * @param class-string $gatewayClass
     *
     * @return SerializerInterface
     */
    public static function createGatewaySerializer(string $gatewayClass): SerializerInterface
    {
        $serializers = [
            AkbankPosSerializer::class,
            EstPosSerializer::class,
            GarantiPosSerializer::class,
            InterPosSerializer::class,
            KuveytPosSerializer::class,
            ParamPosSerializer::class,
            PayFlexCPV4PosSerializer::class,
            PayFlexV4PosSerializer::class,
            PayForPosSerializer::class,
            PosNetSerializer::class,
            PosNetV1PosSerializer::class,
            ToslaPosSerializer::class,
            VakifKatilimPosSerializer::class,
        ];

        /** @var class-string<SerializerInterface> $serializer */
        foreach ($serializers as $serializer) {
            if ($serializer::supports($gatewayClass)) {
                return new $serializer();
            }
        }

        throw new DomainException(\sprintf('Serializer not found for the gateway %s', $gatewayClass));
    }
}
