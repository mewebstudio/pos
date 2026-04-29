<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\PosNetV1PosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class PosNetV1PosResponseValueMapperTest extends TestCase
{
    private PosNetV1PosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            PosNetV1Pos::class,
            RequestValueMapperFactory::createForGateway(PosNetV1Pos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(PosNetV1Pos::class);
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

    /**
     * @dataProvider mapOrderStatusDataProvider
     */
    public function testMapOrderStatus(
        string $orderStatus,
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
        $this->mapper->mapSecureType('3DModel', PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['TL', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_TRY],
            ['US', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_USD],
            ['EU', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_EUR],
            ['GB', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_GBP],
            ['JP', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_JPY],
            ['RU', PosInterface::TX_TYPE_STATUS, PosInterface::CURRENCY_RUB],
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['840', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_USD],
            ['978', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_EUR],
            ['826', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_GBP],
            ['392', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_JPY],
            ['643', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Sale', PosInterface::TX_TYPE_PAY_AUTH],
            ['Auth', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['Capture', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['Reverse', PosInterface::TX_TYPE_CANCEL],
            ['Return', PosInterface::TX_TYPE_REFUND],
            ['TransactionInquiry', PosInterface::TX_TYPE_STATUS],
            ['', null],
            ['blabla', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            [PosInterface::TX_TYPE_CANCEL, PosInterface::PAYMENT_STATUS_CANCELED],
            [PosInterface::TX_TYPE_REFUND, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['blabla', 'blabla'],
        ];
    }
}
