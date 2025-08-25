<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\ParamPos;
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
 * @covers \Mews\Pos\Client\ParamPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class ParamPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private ParamPosHttpClient $client;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

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
            'payment_api'   => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            // API URL for 3D host payment
            'payment_api_2' => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
        ];

        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::createForGateway(
            ParamPos::class,
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
        $this->assertTrue(ParamPosHttpClient::supports(ParamPos::class));
        $this->assertFalse(ParamPosHttpClient::supports(AkbankPos::class));
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
            '<?xml version="1.0" encoding="" ?><request>data</request>',
            SerializerInterface::FORMAT_XML,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml',
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

        $decodedResponse = ['decoded-response'];
        if ($decodeResponse) {
            $this->serializer->expects($this->exactly(2))
                ->method('decode')
                ->with($responseContent, $txType)
                ->willReturn($decodedResponse);
        } else {
            $this->serializer->expects($this->once())
                ->method('decode')
                ->with($responseContent, $txType)
                ->willReturn($decodedResponse);
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

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            '<?xml version="1.0" encoding="" ?><request>data</request>',
            SerializerInterface::FORMAT_XML,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml',
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

    public function testMissingUrlInConfig(): void
    {
        $client = PosHttpClientFactory::createForGateway(
            ParamPos::class,
            [
                'payment_api' => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            $this->serializer,
            $this->crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $this->expectException(\RuntimeException::class);
        $client->getApiURL(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_HOST);
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
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml',
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

        $decodedResponse = [
            'soap:Fault' => [
                'faultstring' => 'Error message',
            ],
        ];
        $this->serializer->expects($this->once())
            ->method('decode')
            ->with($responseContent, $txType)
            ->willReturn($decodedResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error message');

        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );
    }

    public function testRequestApiUrlNotFound(): void
    {
        $client = PosHttpClientFactory::createForGateway(
            ParamPos::class,
            [
                'payment_api' => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            $this->serializer,
            $this->crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $this->psrClient->expects($this->never())
            ->method('sendRequest');

        $this->expectException(\RuntimeException::class);
        $client->request(
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            PosInterface::MODEL_3D_HOST,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
            ],
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
}
