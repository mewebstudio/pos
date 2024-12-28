<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\RequestDataMapperFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\RequestDataMapperFactory
 */
class RequestDataMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider createGatewayRequestMapperDataProvider
     */
    public function testCreateGatewayRequestMapper(string $gatewayClass, string $mapperClass): void
    {
        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $crypt           = $this->createMock(\Mews\Pos\Crypt\CryptInterface::class);
        $currencies      = [];
        $mapper          = RequestDataMapperFactory::createGatewayRequestMapper(
            $gatewayClass,
            $eventDispatcher,
            $crypt,
            $currencies
        );
        $this->assertInstanceOf($mapperClass, $mapper);
    }

    public function testCreateGatewayRequestMapperUnsupported(): void
    {
        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $crypt           = $this->createMock(\Mews\Pos\Crypt\CryptInterface::class);
        $currencies      = [];
        $this->expectException(\DomainException::class);
        RequestDataMapperFactory::createGatewayRequestMapper(
            \stdClass::class,
            $eventDispatcher,
            $crypt,
            $currencies
        );
    }

    public static function createGatewayRequestMapperDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\EstPos::class, \Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNet::class, \Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper::class],
        ];
    }
}
