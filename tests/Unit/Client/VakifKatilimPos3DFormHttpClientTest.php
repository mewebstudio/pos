<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\VakifKatilimPos3DFormHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
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

/**
 * @covers \Mews\Pos\Client\VakifKatilimPos3DFormHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class VakifKatilimPos3DFormHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private VakifKatilimPos3DFormHttpClient $client;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

    /** @var RequestValueMapperInterface & MockObject */
    private RequestValueMapperInterface $requestValueMapper;

    /** @var ClientInterface & MockObject */
    private ClientInterface $psrClient;

    /** @var RequestFactoryInterface & MockObject */
    private RequestFactoryInterface $requestFactory;

    /** @var StreamFactoryInterface & MockObject */
    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $this->serializer         = $this->createMock(SerializerInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            VakifKatilimPos3DFormHttpClient::class,
            'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
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
        $this->assertTrue($this->client::supports(VakifKatilimPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(VakifKatilimPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE));
        $this->assertFalse($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
    }

    public function testRequest(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['foo' => 'bar'];
        $order        = ['order_id' => '123'];
        $url          = 'https://test.url';

        $encodedData = new EncodedData('encoded-content', SerializerInterface::FORMAT_XML);
        $this->serializer->expects($this->once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->serializer->expects($this->never())
            ->method('decode');

        $request     = $this->prepareHttpRequest($encodedData->getData(), [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
        ]);

        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $url)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $result = $this->client->request($txType, $paymentModel, $requestData, $order, $url);

        $this->assertSame($responseContent, $result);
    }
}
