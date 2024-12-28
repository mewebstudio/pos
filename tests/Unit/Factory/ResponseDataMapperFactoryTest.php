<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Factory\ResponseDataMapperFactory
 */
class ResponseDataMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider createGatewayResponseMapperDataProvider
     */
    public function testCreateGatewayResponseMapper(string $gatewayClass, string $mapperClass): void
    {
        $requestDataMapper = $this->createMock(RequestDataMapperInterface::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $mapper            = ResponseDataMapperFactory::createGatewayResponseMapper(
            $gatewayClass,
            $requestDataMapper,
            $logger
        );
        $this->assertInstanceOf($mapperClass, $mapper);
    }

    public function testCreateGatewayResponseMapperUnsupported(): void
    {
        $requestDataMapper = $this->createMock(RequestDataMapperInterface::class);
        $logger            = $this->createMock(LoggerInterface::class);
        $this->expectException(\DomainException::class);
        ResponseDataMapperFactory::createGatewayResponseMapper(
            \stdClass::class,
            $requestDataMapper,
            $logger
        );
    }

    public static function createGatewayResponseMapperDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\EstPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, \Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PosNet::class, \Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper::class],
        ];
    }
}
