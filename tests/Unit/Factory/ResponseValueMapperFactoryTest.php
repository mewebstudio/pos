<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

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
use Mews\Pos\DataMapper\ResponseValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\ResponseValueMapperFactory
 */
class ResponseValueMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(
        string $gatewayClass,
        string $expectedFormatterClass,
        bool $txTypeMappingSupported,
        bool $currencyMappingSupported,
        bool $secureTypeMappingSupported
    ): void {
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        if ($txTypeMappingSupported) {
            $requestValueMapper->expects($this->once())
                ->method('getTxTypeMappings')
                ->willReturn([
                    PosInterface::TX_TYPE_PAY_AUTH => 'Auth',
                ]);
        } else {
            $requestValueMapper->expects($this->never())
                ->method('getTxTypeMappings');
        }
        if ($secureTypeMappingSupported) {
            $requestValueMapper->expects($this->once())
                ->method('getSecureTypeMappings')
                ->willReturn([
                    PosInterface::MODEL_3D_SECURE => '3D',
                ]);
        } else {
            $requestValueMapper->expects($this->never())
                ->method('getSecureTypeMappings');
        }
        if ($currencyMappingSupported) {
            $requestValueMapper->expects($this->once())
                ->method('getCurrencyMappings')
                ->willReturn([
                    PosInterface::CURRENCY_EUR => '978',
                ]);
        } else {
            $requestValueMapper->expects($this->never())
                ->method('getCurrencyMappings');
        }
        $this->assertInstanceOf(
            $expectedFormatterClass,
            $valueMapper = ResponseValueMapperFactory::createForGateway($gatewayClass, $requestValueMapper)
        );

        if ($txTypeMappingSupported) {
            $valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_AUTH);
        }
        if ($currencyMappingSupported) {
            $valueMapper->mapCurrency(PosInterface::CURRENCY_EUR);
        }
        if ($secureTypeMappingSupported) {
            $valueMapper->mapSecureType(PosInterface::MODEL_3D_SECURE);
        }
    }

    public function testCreateForGatewayInvalidGateway(): void
    {
        $this->expectException(\DomainException::class);
        ResponseValueMapperFactory::createForGateway(
            \stdClass::class,
            $this->createMock(RequestValueMapperInterface::class)
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosResponseValueMapper::class, true, true, false],
            [EstPos::class, EstPosResponseValueMapper::class, false, true, true],
            [EstV3Pos::class, EstPosResponseValueMapper::class, false, true, true],
            [GarantiPos::class, GarantiPosResponseValueMapper::class, true, true, true],
            [InterPos::class, InterPosResponseValueMapper::class, false, true, false],
            [KuveytPos::class, BoaPosResponseValueMapper::class, true, true, true],
            [KuveytSoapApiPos::class, BoaPosResponseValueMapper::class, true, true, true],
            [Param3DHostPos::class, ParamPosResponseValueMapper::class, false, false, false],
            [ParamPos::class, ParamPosResponseValueMapper::class, false, false, false],
            [PayForPos::class, PayForPosResponseValueMapper::class, true, true, true],
            [PayFlexV4Pos::class, PayFlexV4PosResponseValueMapper::class, true, true, false],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosResponseValueMapper::class, true, true, false],
            [PosNet::class, PosNetResponseValueMapper::class, true, true, false],
            [PosNetV1Pos::class, PosNetV1PosResponseValueMapper::class, true, true, false],
            [ToslaPos::class, ToslaPosResponseValueMapper::class, true, true, false],
            [VakifKatilimPos::class, BoaPosResponseValueMapper::class, true, true, true],
        ];
    }
}
