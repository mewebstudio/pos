<?php
/**
 * @license MIT
 */
declare(strict_types=1);

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Client\PosNetPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\ClientFactory;
use Mews\Pos\Gateways\AkbankPos;
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
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\Client\AkbankPosHttpClientTest;
use Mews\Pos\Tests\Unit\Client\KuveytPosHttpClientTest;
use Mews\Pos\Tests\Unit\Client\ParamPosHttpClientTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HttpClientFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $clientClass): void
    {
        $client  = ClientFactory::createForGateway(
            $gatewayClass,
            [],
            $this->createMock(AbstractPosAccount::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(CryptInterface::class),
            $this->createMock(RequestValueMapperInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $this->assertInstanceOf($clientClass, $client);
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosHttpClient::class],
            [EstV3Pos::class, EstPosHttpClient::class],
            [GarantiPos::class, GarantiPosHttpClient::class],
            [InterPos::class, InterPosHttpClient::class],
            [KuveytPos::class, KuveytPosHttpClient::class],
            [ParamPos::class, ParamPosHttpClient::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosHttpClient::class],
            [PayFlexV4Pos::class, PayFlexV4PosHttpClient::class],
            [PayForPos::class, PayForPosHttpClient::class],
            [PosNet::class, PosNetPosHttpClient::class],
            [PosNetV1Pos::class, PosNetV1PosHttpClient::class],
            [ToslaPos::class, ToslaPosHttpClient::class],
            [VakifKatilimPos::class, VakifKatilimPosHttpClient::class],
        ];
    }
}
