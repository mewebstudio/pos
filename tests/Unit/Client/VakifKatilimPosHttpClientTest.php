<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\VakifKatilimPos;
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
 * @covers \Mews\Pos\Client\VakifKatilimPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class VakifKatilimPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private VakifKatilimPosHttpClient $client;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

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
            'payment_api' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
        ];

        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::createForGateway(
            VakifKatilimPos::class,
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

    public function testSupports(): void
    {
        $this->assertTrue(VakifKatilimPosHttpClient::supports(VakifKatilimPos::class));
        $this->assertFalse(VakifKatilimPosHttpClient::supports(AkbankPos::class));
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, ?string $orderTxType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel, $orderTxType);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider getApiUrlDataFailProvider
     */
    public function testGetApiUrlUnsupportedTxType(
        ?string $txType,
        ?string $paymentModel,
        ?string $orderTxType,
        string $expectedException
    ): void {
        $this->expectException($expectedException);
        $this->client->getApiURL($txType, $paymentModel, $orderTxType);
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
            '<?xml version="1.0" encoding="" ?><request>data</request>',
            SerializerInterface::FORMAT_XML,
        );

        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
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
            '<?xml version="1.0" encoding="" ?><request>data</request>',
            SerializerInterface::FORMAT_XML,
        );

        $request = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
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

        $encodedData     = new EncodedData(
            '<?xml version="1.0" encoding="" ?><request>data</request>',
            SerializerInterface::FORMAT_XML,
        );

        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
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
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            PosInterface::MODEL_3D_SECURE,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            'decodeResponse' => true,
        ];

        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            'decodeResponse' => false,
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizaten',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizatenClose',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrderByMerchantOrderId',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_ORDER_HISTORY,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrder',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationDrawBack',
            ],
        ];
    }

    public static function getApiUrlDataFailProvider(): array
    {
        return [
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => null,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => null,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => 'abc',
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_CANCEL,
                'paymentModel'    => PosInterface::MODEL_NON_SECURE,
                'orderTxType'     => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
        ];
    }
}
