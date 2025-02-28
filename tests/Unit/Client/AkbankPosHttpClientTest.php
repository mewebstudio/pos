<?php

/**
 * @license MIT
 */

declare(strict_types=1);

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\ClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\MockObject\MockClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class AkbankPosHttpClientTest extends TestCase
{
    private AkbankPosHttpClient $client;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

    /** @var AbstractPosAccount & MockObject */
    private AbstractPosAccount $account;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var RequestValueMapperInterface & MockObject */
    private RequestValueMapperInterface $requestValueMapper;
    /**
     * @var ClientInterface& MockObject
     */
    private ClientInterface $psr18Client;
    /**
     * @var RequestFactoryInterface& MockObject
     */
    private RequestFactoryInterface $requestFactory;
    /**
     * @var StreamFactoryInterface & MockClass
     */
    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
        ];

        $this->account            = $this->createMock(AbstractPosAccount::class);
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psr18Client        = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = ClientFactory::createForGateway(
            AkbankPos::class,
            $endpoints,
            $this->serializer,
            $this->crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psr18Client,
            $this->requestFactory,
            $this->streamFactory
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

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(AkbankPos::class));
        $this->assertFalse($this->client::supports(EstV3Pos::class));
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testRequest(
        string $txType,
        string $paymentModel,
        array  $requestData,
        array  $order,
        string $expectedApiUrl,
        bool   $decodeResponse
    ): void
    {
        $encodedData     = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $responseContent = 'response-content';
        $requestStream   = $this->createMock(StreamInterface::class);
        $request         = $this->createMock(RequestInterface::class);
        $response        = $this->createMock(ResponseInterface::class);
        $responseStream  = $this->createMock(StreamInterface::class);
        $responseStream->expects($this->atLeastOnce())
            ->method('getContents')
            ->willReturn($responseContent);
        $response->expects($this->atLeastOnce())
            ->method('getBody')
            ->willReturn($responseStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturn($request);
        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnMap([
                [
                    'Content-Type',
                    'application/json',
                    $request,
                ],
                [
                    'auth-hash',
                    'hash123',
                    $request,
                ],
            ]);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with($encodedData->getData())
            ->willReturn($requestStream);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->with($encodedData->getData(), 'store-key123')
            ->willReturn('hash123');


        $this->psr18Client->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        if ($decodeResponse) {
            $decodedResponse = ['decoded-response'];
            $this->serializer->expects($this->once())
                ->method('decode')
                ->with($responseContent, $txType)
                ->willReturn($decodedResponse);
        } else {
            $this->serializer->expects($this->never())
                ->method('decode');
        }

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
            $this->account,
            true,
            $decodeResponse,
        );

        if ($decodeResponse) {
            $this->assertSame($decodedResponse, $actual);
        } else {
            $this->assertSame($responseContent, $actual);
        }
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            'decodeResponse' => true,
        ];

        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            'decodeResponse' => false,
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/portal/report/transaction',
            ],
        ];
    }
}
