<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\KuveytSoapApiPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\KuveytSoapApiPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class KuveytSoapApiPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private KuveytSoapApiPosHttpClient $client;

    /** @var RequestValueMapperInterface&MockObject */
    private RequestValueMapperInterface $requestValueMapper;

    /** @var StreamFactoryInterface&MockObject */
    private StreamFactoryInterface $streamFactory;

    /** @var SerializerInterface&MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var ClientInterface&MockObject */
    private ClientInterface $psrClient;

    /** @var RequestFactoryInterface&MockObject */
    private RequestFactoryInterface $requestFactory;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api' => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
        ];

        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::createForGateway(
            KuveytSoapApiPos::class,
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
        $this->assertFalse($this->client::supports(AkbankPos::class));
        $this->assertTrue($this->client::supports(KuveytSoapApiPos::class));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE));
        $this->assertFalse($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE));
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
    public function testRequestCreatesCorrectSoapRequest(
        string $txType,
        string $paymentModel,
        array  $requestData,
        array  $order,
        string $expectedApiUrl,
        bool   $decodeResponse
    ): void {
        $requestData     = ['foo' => 'bar'];
        $order           = ['id' => 123];
        $responseContent = 'response-content';
        $encodedData     = new \Mews\Pos\Serializer\EncodedData('encoded-content', SerializerInterface::FORMAT_XML);

        $request  = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
            [
                'name'  => 'SOAPAction',
                'value' => 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/CancelV4',
            ],
        ]);
        $response = $this->prepareHttpResponse($responseContent, 200);

        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willReturn('CancelV4');

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

    public function testCheckFailResponseThrowsExceptionOnEmptyBody(): void
    {
        $paymentModel    = PosInterface::MODEL_NON_SECURE;
        $txType          = PosInterface::TX_TYPE_CANCEL;
        $requestData     = ['foo' => 'bar'];
        $order           = ['id' => 123];
        $responseContent = '';
        $expectedApiUrl  = 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
        $encodedData     = new \Mews\Pos\Serializer\EncodedData('encoded-content', SerializerInterface::FORMAT_XML);

        $request  = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
            [
                'name'  => 'SOAPAction',
                'value' => 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/CancelV4',
            ],
        ]);
        $response = $this->prepareHttpResponse($responseContent, 200);

        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->willReturn('CancelV4');

        $this->serializer->expects($this->once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->serializer->expects($this->never())
            ->method('decode');


        $this->expectException(\RuntimeException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );
    }

    /**
     * @dataProvider failResponseDataProvider
     */
    public function testCheckFailResponseThrowsExceptionOnSoapFault(array $decodedResponse, string $expectedExpMsg): void
    {
        $paymentModel    = PosInterface::MODEL_NON_SECURE;
        $txType          = PosInterface::TX_TYPE_CANCEL;
        $requestData     = ['foo' => 'bar'];
        $order           = ['id' => 123];
        $responseContent = 'response-content';
        $expectedApiUrl  = 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
        $encodedData     = new \Mews\Pos\Serializer\EncodedData('encoded-content', SerializerInterface::FORMAT_XML);

        $request  = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
            [
                'name'  => 'SOAPAction',
                'value' => 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/CancelV4',
            ],
        ]);
        $response = $this->prepareHttpResponse($responseContent, 400);

        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->willReturn('CancelV4');

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
            ->willReturn($decodedResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedExpMsg);
        $this->expectExceptionCode(400);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );
    }


    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
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
            'expectedApiUrl' => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            'decodeResponse' => true,
        ];

        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['request-data'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            'decodeResponse' => false,
        ];
    }

    public static function failResponseDataProvider(): array
    {
        return [
            [
                'decodedResponse' => [
                    's:Fault' => [
                        'faultstring' => [
                            '#' => 'Some SOAP Fault',
                        ],
                    ],
                ],
                'expectedExpMsg'  => 'Some SOAP Fault',
            ],
            [
                'decodedResponse' => [
                    's:Fault' => [
                        'some_other_key' => 'bla',
                    ],
                ],
                'expectedExpMsg'  => 'Bankaya istek başarısız!',
            ],
        ];
    }
}
