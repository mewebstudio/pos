<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\PayFlexV4Pos3DFormHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\PayFlexV4Pos;
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
 * @covers \Mews\Pos\Client\PayFlexV4Pos3DFormHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class PayFlexV4Pos3DFormHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PayFlexV4Pos3DFormHttpClient $client;

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
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::create(
            PayFlexV4Pos3DFormHttpClient::class,
            'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
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
        $this->assertTrue($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE));
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
        string $encodedRequestData,
        array  $order,
        string $expectedApiUrl
    ): void {
        $encodedData = new EncodedData(
            $encodedRequestData,
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->serializer->expects($this->never())
            ->method('encode');

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $decodedResponse = ['decoded-response'];
        $this->serializer->expects($this->once())
            ->method('decode')
            ->with($responseContent, $txType)
            ->willReturn($decodedResponse);

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl
        );

        $this->assertSame($decodedResponse, $actual);
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['key1' => 'val1'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            'key1=val1',
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->serializer->expects($this->never())
            ->method('encode');

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
        $requestData    = ['key1' => 'val1'];
        $order          = ['id' => 123];

        $encodedData = new EncodedData(
            'key1=val1',
            SerializerInterface::FORMAT_FORM,
        );
        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 500);

        $this->serializer->expects($this->never())
            ->method('encode');

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
                'txType'       => PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['key1' => 'val1'],
            'encodedRequestData' => 'key1=val1',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
        ];
    }
}
