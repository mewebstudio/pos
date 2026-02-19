<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Client\PosNetV1PosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class PosNetV1PosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PosNetV1PosHttpClient $client;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

    /**
     * @var ClientInterface&MockObject
     */
    private ClientInterface $psrClient;
    /**
     * @var RequestFactoryInterface& MockObject
     */
    private RequestFactoryInterface $requestFactory;

    /**
     * @var StreamFactoryInterface&MockObject
     */
    private StreamFactoryInterface $streamFactory;
    /**
     * @var RequestValueMapperInterface&MockObject
     */
    private $requestValueMapper;

    protected function setUp(): void
    {
        $endpoints                = [
            'payment_api' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
        ];
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::createForGateway(
            PosNetV1Pos::class,
            $endpoints,
            $this->serializer,
            $crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $apiUri, string $expected): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willReturn($apiUri);

        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL();
    }

    public function testSupports(): void
    {
        $this->assertTrue(PosNetV1PosHttpClient::supports(PosNetV1Pos::class));
        $this->assertFalse(PosNetV1PosHttpClient::supports(PosNet::class));
    }

    public function testSupportsTx(): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with(PosInterface::TX_TYPE_PAY_AUTH)
            ->willReturn('Sale');

        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
    }

    public function testSupportsTxWithUnsupportedTx(): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with('unsupported')
            ->willThrowException(new UnsupportedTransactionTypeException());

        $this->assertFalse($this->client->supportsTx('unsupported', PosInterface::MODEL_3D_SECURE));
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
    ): void {
        $encodedData = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
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
            null,
            true,
            $decodeResponse,
        );

        if ($decodeResponse) {
            $this->assertSame($decodedResponse, $actual);
        } else {
            $this->assertSame($responseContent, $actual);
        }
    }

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 500);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('İstek Başarısız!');

        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->serializer->expects($this->once())
            ->method('decode')
            ->willThrowException(new NotEncodableValueException());

        $this->expectException(NotEncodableValueException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );
    }

    public function testRequestApiUrlNotFound(): void
    {
        $this->psrClient->expects($this->never())
            ->method('sendRequest');
        $this->requestValueMapper->expects(self::once())
            ->method('mapTxType')
            ->with(PosInterface::TX_TYPE_PAY_POST_AUTH)
            ->willThrowException(new UnsupportedTransactionTypeException());

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->client->request(
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            PosInterface::MODEL_3D_SECURE,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'apiUri'       => 'Sale',
                'expected'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'apiUri'       => 'Reverse',
                'expected'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Reverse',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            'decodeResponse' => true,
        ];

        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            'decodeResponse' => false,
        ];
    }
}
