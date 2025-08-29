<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\RequestValueFormatter\AkbankPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\EstPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\InterPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\RequestValueFormatterFactory
 */
class RequestValueFormatterFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $this->assertInstanceOf(
            $expectedFormatterClass,
            RequestValueFormatterFactory::createForGateway($gatewayClass)
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [ToslaPos::class, ToslaPosRequestValueFormatter::class],
            [AkbankPos::class, AkbankPosRequestValueFormatter::class],
            [EstPos::class, EstPosRequestValueFormatter::class],
            [EstV3Pos::class, EstPosRequestValueFormatter::class],
            [GarantiPos::class, GarantiPosRequestValueFormatter::class],
            [InterPos::class, InterPosRequestValueFormatter::class],
            [KuveytPos::class, KuveytPosRequestValueFormatter::class],
            [KuveytSoapApiPos::class, KuveytPosRequestValueFormatter::class],
            [VakifKatilimPos::class, VakifKatilimPosRequestValueFormatter::class],
            [ParamPos::class, ParamPosRequestValueFormatter::class],
            [PayForPos::class, PayForPosRequestValueFormatter::class],
            [PosNet::class, PosNetRequestValueFormatter::class],
            [PosNetV1Pos::class, PosNetV1PosRequestValueFormatter::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosRequestValueFormatter::class],
        ];
    }
}
