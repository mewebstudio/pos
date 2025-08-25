<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use DomainException;
use Mews\Pos\Client\KuveytSoapApiPosSoapClient;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosSoapClientFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PosSoapClientFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $clientClass): void
    {
        $client  = PosSoapClientFactory::createForGateway(
            $gatewayClass,
            [],
            $this->createMock(RequestValueMapperInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $this->assertInstanceOf($clientClass, $client);
    }

    /**
     * @dataProvider createForGatewayFailDataProvider
     */
    public function testCreateForGatewayFail(string $gatewayClass, string $expectedException): void
    {
        $this->expectException($expectedException);
        PosSoapClientFactory::createForGateway(
            $gatewayClass,
            [],
            $this->createMock(RequestValueMapperInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [KuveytSoapApiPos::class, KuveytSoapApiPosSoapClient::class],
        ];
    }

    public static function createForGatewayFailDataProvider(): array
    {
        return [
            [KuveytPos::class, DomainException::class],
        ];
    }
}
