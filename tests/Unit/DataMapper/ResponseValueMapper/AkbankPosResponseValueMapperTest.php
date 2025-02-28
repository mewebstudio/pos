<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AkbankPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class AkbankPosResponseValueMapperTest extends TestCase
{
    private AkbankPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            AkbankPos::class,
            RequestValueMapperFactory::createForGateway(AkbankPos::class)
        );
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
    }

    /**
     * @dataProvider mapOrderStatusDataProvider
     */
    public function testMapOrderStatus(
        string $orderStatus,
        ?string $preAuthStatus,
        bool   $isRecurringOrder,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->mapper->mapOrderStatus($orderStatus, $preAuthStatus, $isRecurringOrder)
        );
    }

    /**
     * @dataProvider mapCurrencyDataProvider
     */
    public function testMapCurrency(int $currency, string $txType, ?string $expected): void
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
            [949, PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            [949, '', PosInterface::CURRENCY_TRY],
            [840, '', PosInterface::CURRENCY_USD],
            [978, '', PosInterface::CURRENCY_EUR],
            [826, '', PosInterface::CURRENCY_GBP],
            [392, '', PosInterface::CURRENCY_JPY],
            [643, '', PosInterface::CURRENCY_RUB],
            [1, '', null],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['1000', PosInterface::TX_TYPE_PAY_AUTH],
            ['3000', PosInterface::TX_TYPE_PAY_AUTH],
            ['1004', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['3004', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['1005', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['1002', PosInterface::TX_TYPE_REFUND],
            ['1003', PosInterface::TX_TYPE_CANCEL],
            ['1010', PosInterface::TX_TYPE_ORDER_HISTORY],
            ['1009', PosInterface::TX_TYPE_HISTORY],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['N', null, false, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['N', 'O', false, PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED],
            ['N', 'C', false, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['S', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['V', null, false, PosInterface::PAYMENT_STATUS_CANCELED],
            ['R', null, false, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['Başarılı', null, false, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['Başarısız', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['İptal', null, false, PosInterface::PAYMENT_STATUS_CANCELED],
            ['S', null, true, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['W', null, true, PosInterface::PAYMENT_STATUS_PAYMENT_PENDING],
            ['V', null, true, PosInterface::PAYMENT_STATUS_CANCELED],
            ['C', null, true, PosInterface::PAYMENT_STATUS_CANCELED],
            ['blabla', null, true, 'blabla'],
            ['blabla', null, false, 'blabla'],
        ];
    }
}
