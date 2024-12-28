<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\CryptFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Factory\CryptFactory
 */
class CryptFactoryTest extends TestCase
{
    /**
     * @dataProvider createGatewayCryptDataProvider
     */
    public function testCreateGatewayCrypt(string $gatewayClass, string $serializerClass): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $crypt  = CryptFactory::createGatewayCrypt($gatewayClass, $logger);
        $this->assertInstanceOf($serializerClass, $crypt);
    }

    public static function createGatewayCryptDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\Crypt\AkbankPosCrypt::class],
            [\Mews\Pos\Gateways\EstPos::class, \Mews\Pos\Crypt\EstPosCrypt::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\Crypt\EstV3PosCrypt::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\Crypt\GarantiPosCrypt::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\Crypt\InterPosCrypt::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, \Mews\Pos\Crypt\NullCrypt::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\Crypt\PayFlexCPV4Crypt::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\Crypt\PayForPosCrypt::class],
            [\Mews\Pos\Gateways\PosNet::class, \Mews\Pos\Crypt\PosNetCrypt::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\Crypt\PosNetV1PosCrypt::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\Crypt\ToslaPosCrypt::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\stdClass::class, \Mews\Pos\Crypt\NullCrypt::class],
        ];
    }
}
