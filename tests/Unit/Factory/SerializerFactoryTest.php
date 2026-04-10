<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\HttpClientInterface;
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
    public function testCreateGatewaySerializer(string $gatewayClass, ?string $apiName, string $serializerClass): void
    {
        $serializer = SerializerFactory::createGatewaySerializer($gatewayClass, $apiName);
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
            [\Mews\Pos\Gateways\AkbankPos::class, null, \Mews\Pos\Serializer\AkbankPosSerializer::class],
            [\Mews\Pos\Gateways\EstPos::class, null, \Mews\Pos\Serializer\EstPosSerializer::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, null, \Mews\Pos\Serializer\EstPosSerializer::class],
            [\Mews\Pos\Gateways\GarantiPos::class, null, \Mews\Pos\Serializer\GarantiPosSerializer::class],
            [\Mews\Pos\Gateways\InterPos::class, null, \Mews\Pos\Serializer\InterPosSerializer::class],
            [\Mews\Pos\Gateways\KuveytPos::class, HttpClientInterface::API_NAME_PAYMENT_API, \Mews\Pos\Serializer\KuveytPosSerializer::class],
            [\Mews\Pos\Gateways\KuveytSoapApiPos::class, HttpClientInterface::API_NAME_QUERY_API, \Mews\Pos\Serializer\KuveytSoapApiPosSerializer::class],
            [\Mews\Pos\Gateways\ParamPos::class, null, \Mews\Pos\Serializer\ParamPosSerializer::class],
            [\Mews\Pos\Gateways\Param3DHostPos::class, null, \Mews\Pos\Serializer\ParamPosSerializer::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, null, \Mews\Pos\Serializer\PayFlexCPV4PosSerializer::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, HttpClientInterface::API_NAME_PAYMENT_API, \Mews\Pos\Serializer\PayFlexV4PosSerializer::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, HttpClientInterface::API_NAME_QUERY_API, \Mews\Pos\Serializer\PayFlexV4PosSearchApiSerializer::class],
            [\Mews\Pos\Gateways\PayForPos::class, null, \Mews\Pos\Serializer\PayForPosSerializer::class],
            [\Mews\Pos\Gateways\PosNet::class, null, \Mews\Pos\Serializer\PosNetSerializer::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, null, \Mews\Pos\Serializer\PosNetV1PosSerializer::class],
            [\Mews\Pos\Gateways\ToslaPos::class, null, \Mews\Pos\Serializer\ToslaPosSerializer::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, null, \Mews\Pos\Serializer\VakifKatilimPosSerializer::class],
        ];
    }
}
