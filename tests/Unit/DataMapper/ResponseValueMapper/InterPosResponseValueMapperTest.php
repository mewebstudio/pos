<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\InterPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\InterPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class InterPosResponseValueMapperTest extends TestCase
{
    private InterPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            InterPos::class,
            RequestValueMapperFactory::createForGateway(InterPos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(InterPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    public function testMapTxType(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapTxType('Auth');
    }

    public function testMapOrderStatus(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapOrderStatus('S');
    }

    /**
     * @dataProvider mapCurrencyDataProvider
     */
    public function testMapCurrency(string $currency, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCurrency($currency, $txType));
    }

    public function testMapSecureType(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapSecureType('3DModel', PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }
}
