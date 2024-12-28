<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\SerializerFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\SerializerFactory
 */
class SerializerFactoryTest extends TestCase
{
    /**
     * @dataProvider createGatewaySerializerDataProvider
     */
    public function testCreateGatewaySerializer(string $gatewayClass, string $serializerClass): void
    {
        $serializer = SerializerFactory::createGatewaySerializer($gatewayClass);
        $this->assertInstanceOf($serializerClass, $serializer);
    }

    public function testCreateGatewaySerializerUnsupported(): void
    {
        $this->expectException(\DomainException::class);
        SerializerFactory::createGatewaySerializer(\stdClass::class);
    }

    public function createGatewaySerializerDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\Serializer\AkbankPosSerializer::class],
            [\Mews\Pos\Gateways\EstPos::class, \Mews\Pos\Serializer\EstPosSerializer::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\Serializer\EstPosSerializer::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\Serializer\GarantiPosSerializer::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\Serializer\InterPosSerializer::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\Serializer\KuveytPosSerializer::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\Serializer\PayFlexCPV4PosSerializer::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, \Mews\Pos\Serializer\PayFlexV4PosSerializer::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\Serializer\PayForPosSerializer::class],
            [\Mews\Pos\Gateways\PosNet::class, \Mews\Pos\Serializer\PosNetSerializer::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\Serializer\PosNetV1PosSerializer::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\Serializer\ToslaPosSerializer::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\Serializer\VakifKatilimPosSerializer::class],
        ];
    }
}
