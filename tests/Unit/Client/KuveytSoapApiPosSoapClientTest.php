<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\KuveytSoapApiPosSoapClient;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosSoapClientFactory;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\KuveytSoapApiPosSoapClient
 * @covers \Mews\Pos\Client\AbstractSoapClient
 */
class KuveytSoapApiPosSoapClientTest extends TestCase
{
    private KuveytSoapApiPosSoapClient $client;

    /** @var RequestValueMapperInterface&MockObject */
    private RequestValueMapperInterface $requestValueMapper;

    protected function setUp(): void
    {
        $endpoints = [
            'payment_api' => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
        ];

        $logger                   = $this->createMock(LoggerInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);

        $this->client = PosSoapClientFactory::createForGateway(
            KuveytSoapApiPos::class,
            $endpoints,
            $this->requestValueMapper,
            $logger,
        );
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL();

        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["ListOfContinentsByName", false]
     *         [null, true]
     */
    public function testCall(?string $soapAction, bool $isTestMode): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = [];
        $order        = [];
        $url          = 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL';
        $options      = [];
        if (null === $soapAction) {
            $this->requestValueMapper->expects($this->once())
                ->method('mapTxType')
                ->with($txType, $paymentModel, $order)
                ->willReturn('ListOfContinentsByName');
        } else {
            $this->requestValueMapper->expects($this->never())
                ->method('mapTxType');
        }

        $this->client->setTestMode($isTestMode);
        $response = $this->client->call($txType, $paymentModel, $requestData, $order, $soapAction, $url, $options);

        $this->assertNotEmpty($response['ListOfContinentsByNameResult']);
    }

    public function testCallFail(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = [];
        $order        = [];
        $soapAction   = 'InvalidSoapAction';
        $url          = 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL';
        $options      = [];

        $this->expectException(\SoapFault::class);
        $this->client->call($txType, $paymentModel, $requestData, $order, $soapAction, $url, $options);
    }


    public function testIsTestMode(): void
    {
        $this->assertSame(false, $this->client->isTestMode());
        $this->client->setTestMode(true);
        $this->assertSame(true, $this->client->isTestMode());
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL',
            ],
        ];
    }
}
