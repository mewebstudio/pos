<?php

/**
 * @license MIT
 */

declare(strict_types=1);

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\ClientFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EstPosHttpClientTest extends TestCase
{

    private EstPosHttpClient $client;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
        ];

        $serializer = $this->createMock(SerializerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $account = $this->createMock(AbstractPosAccount::class);
        $crypt = $this->createMock(CryptInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);


        $this->client = ClientFactory::createForGateway(
            EstV3Pos::class,
            $endpoints,
            $account,
            $serializer,
            $crypt,
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
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
        ];
    }
}
