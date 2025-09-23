<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\BoaPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\BoaPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class BoaPosResponseValueMapperTest extends TestCase
{
    private BoaPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            KuveytPos::class,
            RequestValueMapperFactory::createForGateway(KuveytPos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(KuveytPos::class);
        $this->assertTrue($result);
        $result = $this->mapper::supports(KuveytSoapApiPos::class);
        $this->assertTrue($result);
        $result = $this->mapper::supports(VakifKatilimPos::class);
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
        $orderStatus,
        $expected
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

    /**
     * @dataProvider mapSecureTypeDataProvider
     */
    public function testMapSecureType(string $secureType, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapSecureType($secureType, $txType));
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['0949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['0949', '', PosInterface::CURRENCY_TRY],
            ['TRY', '', null],
            ['840', '', PosInterface::CURRENCY_USD],
            ['0840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', null],
            ['392', '', null],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Sale', PosInterface::TX_TYPE_PAY_AUTH],
            ['SaleReversal', PosInterface::TX_TYPE_CANCEL],
            ['GetMerchantOrderDetail', PosInterface::TX_TYPE_STATUS],
            ['Drawback', PosInterface::TX_TYPE_REFUND],
            ['PartialDrawback', PosInterface::TX_TYPE_REFUND_PARTIAL],
            ['', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            [1, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            [4, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            [5, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            [6, PosInterface::PAYMENT_STATUS_CANCELED],
            [2, 2],
            ['blabla', 'blabla'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE],
            ['1', PosInterface::TX_TYPE_PAY_AUTH, null],
        ];
    }
}
