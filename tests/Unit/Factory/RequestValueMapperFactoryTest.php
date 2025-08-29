<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\RequestValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\EstPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\InterPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayFlexV4PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PosNetRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\PosNetV1PosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
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
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\RequestValueMapperFactory
 */
class RequestValueMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $this->assertInstanceOf(
            $expectedFormatterClass,
            RequestValueMapperFactory::createForGateway($gatewayClass)
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosRequestValueMapper::class],
            [EstPos::class, EstPosRequestValueMapper::class],
            [EstV3Pos::class, EstPosRequestValueMapper::class],
            [GarantiPos::class, GarantiPosRequestValueMapper::class],
            [InterPos::class, InterPosRequestValueMapper::class],
            [KuveytPos::class, KuveytPosRequestValueMapper::class],
            [KuveytSoapApiPos::class, KuveytPosRequestValueMapper::class],
            [ParamPos::class, ParamPosRequestValueMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosRequestValueMapper::class],
            [PayFlexV4Pos::class, PayFlexV4PosRequestValueMapper::class],
            [PayForPos::class, PayForPosRequestValueMapper::class],
            [PosNet::class, PosNetRequestValueMapper::class],
            [PosNetV1Pos::class, PosNetV1PosRequestValueMapper::class],
            [ToslaPos::class, ToslaPosRequestValueMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosRequestValueMapper::class],
        ];
    }
}
