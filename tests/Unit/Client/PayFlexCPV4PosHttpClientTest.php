<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
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
 * @covers \Mews\Pos\Client\PayFlexCPV4PosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class PayFlexCPV4PosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PayFlexCPV4PosHttpClient $client;

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
            'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
        ];

        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::createForGateway(
            PayFlexCPV4Pos::class,
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
        $this->assertTrue(PayFlexCPV4PosHttpClient::supports(PayFlexCPV4Pos::class));
        $this->assertFalse(PayFlexCPV4PosHttpClient::supports(AkbankPos::class));
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
            'abc=1&sa=aa',
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
            [
                'name'  => 'Accept',
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

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            'abc=1&sa=aa',
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
            [
                'name'  => 'Accept',
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

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            'abc=1&sa=aa',
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
            [
                'name'  => 'Accept',
                'value' => 'text/xml',
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

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
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
