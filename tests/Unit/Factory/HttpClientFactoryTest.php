<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Client\KuveytPos3DFormHttpClient;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Client\PayFlexCPV4Pos3DFormHttpClient;
use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4Pos3DFormHttpClient;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4PosSearchApiHttpClient;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Client\PosNetPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Client\VakifKatilimPos3DFormHttpClient;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class HttpClientFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $clientClass): void
    {
        $client  = PosHttpClientFactory::create(
            $clientClass,
            '',
            $this->createMock(SerializerInterface::class),
            $this->createMock(CryptInterface::class),
            $this->createMock(RequestValueMapperInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
        );

        $this->assertInstanceOf($clientClass, $client);
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [AkbankPosHttpClient::class],
            [EstPosHttpClient::class],
            [GarantiPosHttpClient::class],
            [InterPosHttpClient::class],
            [KuveytPos3DFormHttpClient::class],
            [KuveytPosHttpClient::class],
            [ParamPosHttpClient::class],
            [ParamPosHttpClient::class],
            [PayFlexCPV4Pos3DFormHttpClient::class],
            [PayFlexCPV4PosHttpClient::class],
            [PayFlexV4Pos3DFormHttpClient::class],
            [PayFlexV4PosHttpClient::class],
            [PayFlexV4PosSearchApiHttpClient::class],
            [PayForPosHttpClient::class],
            [PosNetPosHttpClient::class],
            [PosNetV1PosHttpClient::class],
            [ToslaPosHttpClient::class],
            [VakifKatilimPos3DFormHttpClient::class],
            [VakifKatilimPosHttpClient::class],
        ];
    }
}
