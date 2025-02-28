<?php

/**
 * @license MIT
 */

declare(strict_types=1);

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\ClientFactory;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ParamPosHttpClientTest extends TestCase
{

    private ParamPosHttpClient $client;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            // API URL for 3D host payment
            'payment_api_2'   => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
            'gateway_3d_host' => 'https://test-pos.param.com.tr/default.aspx',
        ];

        $account            = $this->createMock(AbstractPosAccount::class);
        $serializer         = $this->createMock(SerializerInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $crypt              = $this->createMock(CryptInterface::class);


        $this->client = ClientFactory::createForGateway(
            ParamPos::class,
            $endpoints,
            $account,
            $serializer,
            $crypt,
            $requestValueMapper,
            $logger,
        );
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
        ];
    }
}
