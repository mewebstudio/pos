<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use InvalidArgumentException;
use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstV3Pos;
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
 * @covers \Mews\Pos\Client\AkbankPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class AkbankPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

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
    private ClientInterface $psrClient;
    /**
     * @var RequestFactoryInterface& MockObject
     */
    private RequestFactoryInterface $requestFactory;
    /**
     * @var StreamFactoryInterface & MockObject
     */
    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
        ];

        $this->account            = $this->createMock(AbstractPosAccount::class);
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::createForGateway(
            AkbankPos::class,
            $endpoints,
            $this->serializer,
            $this->crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(AkbankPosHttpClient::supports(AkbankPos::class));
        $this->assertFalse(AkbankPosHttpClient::supports(EstV3Pos::class));
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
    public function testGetApiUrlException(?string $txType, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->client->getApiURL($txType);
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
        $encodedData     = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $responseContent = 'response-content';
        $request = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name' => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->with($encodedData->getData(), 'store-key123')
            ->willReturn('hash123');


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

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

        $encodedData     = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $responseContent = 'response-content';
        $request = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name' => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->with($encodedData->getData(), 'store-key123')
            ->willReturn('hash123');


        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $decodedResponse = [
            'message' => 'Error message',
            'code'    => 222,
        ];
        $this->serializer->expects($this->once())
            ->method('decode')
            ->with($responseContent, $txType)
            ->willReturn($decodedResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error message');
        $this->expectExceptionCode(222);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
            $this->account,
        );
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData     = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );
        $responseContent = 'response-content';
        $request = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name' => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->serializer->expects($this->once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->willReturn('hash123');


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
            $order,
            null,
            $this->account,
        );
    }

    public function testRequestWithoutAccount(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

        $encodedData = new EncodedData(
            '{"a": "b"}',
            SerializerInterface::FORMAT_JSON,
        );

        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->psrClient->expects($this->never())
            ->method('sendRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );
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

    public static function getApiUrlExceptionDataProvider(): array
    {
        return [
            [
                'txType'          => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
        ];
    }
}
