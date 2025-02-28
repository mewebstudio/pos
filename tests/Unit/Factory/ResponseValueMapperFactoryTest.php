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
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\ResponseValueMapperFactory
 */
class ResponseValueMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->assertInstanceOf(
            $expectedFormatterClass,
            ResponseValueMapperFactory::createForGateway($gatewayClass, $requestValueMapper)
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosResponseValueMapper::class],
            [EstPos::class, EstPosResponseValueMapper::class],
            [EstV3Pos::class, EstPosResponseValueMapper::class],
            [GarantiPos::class, GarantiPosResponseValueMapper::class],
            [InterPos::class, InterPosResponseValueMapper::class],
            [KuveytPos::class, BoaPosResponseValueMapper::class],
            [ParamPos::class, ParamPosResponseValueMapper::class],
            [PayForPos::class, PayForPosResponseValueMapper::class],
            [PayFlexV4Pos::class, PayFlexV4PosResponseValueMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosResponseValueMapper::class],
            [PosNet::class, PosNetResponseValueMapper::class],
            [PosNetV1Pos::class, PosNetV1PosResponseValueMapper::class],
            [ToslaPos::class, ToslaPosResponseValueMapper::class],
            [VakifKatilimPos::class, BoaPosResponseValueMapper::class],
        ];
    }
}
