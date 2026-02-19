<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\GenericPosHttpClientStrategy;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientStrategyFactory;
use Mews\Pos\Gateways\AkbankPos;
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
use Psr\Log\LoggerInterface;

class PosHttpClientStrategyFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass): void
    {
        $crypt = $this->createMock(CryptInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);


        $clientStrategy = PosHttpClientStrategyFactory::createForGateway(
            $gatewayClass,
            [
                'payment_api' => 'https://example.com/api',
            ],
            $crypt,
            $requestValueMapper,
            $logger
        );

        $this->assertInstanceOf(GenericPosHttpClientStrategy::class, $clientStrategy);
    }

    public function testCreateForGatewayWithUnsupportedGateway(): void
    {
        $crypt = $this->createMock(CryptInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Client not found for the gateway UnsupportedGateway');

        PosHttpClientStrategyFactory::createForGateway(
            'UnsupportedGateway',
            [
                'payment_api' => 'https://example.com/api',
            ],
            $crypt,
            $requestValueMapper,
            $logger
        );
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [AkbankPos::class],
            [EstV3Pos::class],
            [GarantiPos::class],
            [InterPos::class],
            [KuveytPos::class],
            [KuveytSoapApiPos::class],
            [ParamPos::class],
            [PayFlexCPV4Pos::class],
            [PayFlexV4Pos::class],
            [PayForPos::class],
            [PosNet::class],
            [PosNetV1Pos::class],
            [ToslaPos::class],
            [VakifKatilimPos::class],
        ];
    }
}
