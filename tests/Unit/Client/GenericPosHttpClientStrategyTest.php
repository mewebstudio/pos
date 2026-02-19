<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\GenericPosHttpClientStrategy;
use Mews\Pos\Client\HttpClientInterface;
use PHPUnit\Framework\TestCase;

class GenericPosHttpClientStrategyTest extends TestCase
{
    public function testGetClientSuccess(): void
    {
        $client1 = $this->createMock(HttpClientInterface::class);
        $client1->method('supportsTx')->willReturn(false);

        $client2 = $this->createMock(HttpClientInterface::class);
        $client2->method('supportsTx')->with('pay_auth', 'non_secure')->willReturn(true);

        $strategy = new GenericPosHttpClientStrategy([
            'payment_api' => $client1,
            'query_api' => $client2,
        ]);

        $this->assertSame($client2, $strategy->getClient('pay_auth', 'non_secure'));
    }

    public function testGetClientThrowsExceptionIfNotFound(): void
    {
        $client1 = $this->createMock(HttpClientInterface::class);
        $client1->method('supportsTx')->willReturn(false);

        $strategy = new GenericPosHttpClientStrategy([
            'payment_api' => $client1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No HTTP client configured for transaction type: pay_auth');

        $strategy->getClient('pay_auth', 'non_secure');
    }
}
