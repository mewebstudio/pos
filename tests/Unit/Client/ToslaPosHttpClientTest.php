<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\ToslaPos;
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
 * @covers \Mews\Pos\Client\ToslaPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class ToslaPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private ToslaPosHttpClient $client;

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
            'payment_api' => 'https://ent.akodepos.com/api/Payment',
        ];
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::createForGateway(
            ToslaPos::class,
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
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider getApiUrlExceptionDataProvider
     */
    public function testGetApiUrlException(?string $txType, ?string $paymentModel, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->client->getApiURL($txType, $paymentModel);
    }

    public function testSupports(): void
    {
        $this->assertTrue(ToslaPosHttpClient::supports(ToslaPos::class));
        $this->assertFalse(ToslaPosHttpClient::supports(PosNet::class));
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

    public function testRequestEmptyResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://entegrasyon.asseco-see.com.tr/fim/api';

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

        $responseContent = '';
        $response        = $this->prepareHttpResponse($responseContent, 204);

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

        $this->serializer->expects($this->never())
            ->method('decode');

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );

        $this->assertSame([], $actual);
    }

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_PAY;
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
        $paymentModel   = PosInterface::MODEL_3D_PAY;
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

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->client->request(
            PosInterface::TX_TYPE_HISTORY,
            PosInterface::MODEL_NON_SECURE,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/Payment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/postAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/inquiry',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/void',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/refund',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/refund',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_ORDER_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/history',
            ],
        ];
    }

    public static function getApiUrlExceptionDataProvider(): array
    {
        return [
            [
                'txType'          => PosInterface::TX_TYPE_HISTORY,
                'paymentModel'    => PosInterface::MODEL_NON_SECURE,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => PosInterface::MODEL_3D_SECURE,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'exception_class' => \InvalidArgumentException::class,
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
