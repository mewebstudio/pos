<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\SoapClient;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Client\SoapClient
 */
class SoapClientTest extends TestCase
{
    private SoapClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new SoapClient($this->createMock(\Psr\Log\LoggerInterface::class));
    }

    /**
     * @testWith [false]
     *          [true]
     */
    public function testCall(bool $isTestMode): void
    {
        $this->client->setTestMode($isTestMode);
        $url         = 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL';
        $soapAction  = 'ListOfCountryNamesByName';
        $requestData = [];

        $response = $this->client->call($url, $soapAction, $requestData);

        $this->assertNotEmpty($response['ListOfCountryNamesByNameResult']);
    }

    public function testCallFail(): void
    {
        $url         = 'https://soap-service-free.mock.beeceptor.com/CountryInfoService?WSDL';
        $soapAction  = 'ListOfCountryNamesByName2';
        $requestData = [];

        $this->expectException(\SoapFault::class);
        $this->client->call($url, $soapAction, $requestData);
    }

    public function testIsTestMode(): void
    {
        $this->assertSame(false, $this->client->isTestMode());
        $this->client->setTestMode(true);
        $this->assertSame(true, $this->client->isTestMode());
    }
}
