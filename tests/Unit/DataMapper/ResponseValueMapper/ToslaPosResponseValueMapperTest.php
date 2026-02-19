<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\ToslaPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class ToslaPosResponseValueMapperTest extends TestCase
{
    private ToslaPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            ToslaPos::class,
            RequestValueMapperFactory::createForGateway(ToslaPos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(ToslaPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType($txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
    }

    /**
     * @dataProvider mapOrderStatusDataProvider
     */
    public function testMapOrderStatus(
        $orderStatus,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->mapper->mapOrderStatus($orderStatus)
        );
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
            ['840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH],
            ['2', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['3', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['4', PosInterface::TX_TYPE_CANCEL],
            ['5', PosInterface::TX_TYPE_REFUND],
            [0, null],
            ['', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            [0, PosInterface::PAYMENT_STATUS_ERROR],
            [1, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            [2, PosInterface::PAYMENT_STATUS_CANCELED],
            [3, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            [4, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['blabla', 'blabla'],
        ];
    }
}
