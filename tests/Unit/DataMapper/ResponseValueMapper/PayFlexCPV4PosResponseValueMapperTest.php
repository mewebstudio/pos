<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexCPV4PosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\PayFlexCPV4PosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class PayFlexCPV4PosResponseValueMapperTest extends TestCase
{
    private PayFlexCPV4PosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            PayFlexCPV4Pos::class,
            RequestValueMapperFactory::createForGateway(PayFlexCPV4Pos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(PayFlexCPV4Pos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
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
        $this->mapper->mapSecureType('3D', PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['TRY', '', null],
            ['840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Sale', PosInterface::TX_TYPE_PAY_AUTH],
            ['Auth', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['Capture', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['Cancel', PosInterface::TX_TYPE_CANCEL],
            ['Refund', PosInterface::TX_TYPE_REFUND],
            ['TxnHistory', PosInterface::TX_TYPE_HISTORY],
            ['OrderInquiry', PosInterface::TX_TYPE_STATUS],
            ['', null],
        ];
    }
}
