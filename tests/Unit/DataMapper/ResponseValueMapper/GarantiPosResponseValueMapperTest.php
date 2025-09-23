<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\GarantiPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class GarantiPosResponseValueMapperTest extends TestCase
{
    private GarantiPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            GarantiPos::class,
            RequestValueMapperFactory::createForGateway(GarantiPos::class)
        );
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
        ?string $requestTxType,
        ?string $txType,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->mapper->mapOrderStatus($orderStatus, $requestTxType, $txType)
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
            ['TL', PosInterface::TX_TYPE_HISTORY, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['TRY', '', null],
            ['840', '', PosInterface::CURRENCY_USD],
            ['USD', PosInterface::TX_TYPE_HISTORY, PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Satis', PosInterface::TX_TYPE_PAY_AUTH],
            ['sales', PosInterface::TX_TYPE_PAY_AUTH],
            ['On Otorizasyon', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['On Otorizasyon Kapama', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['Iade', PosInterface::TX_TYPE_REFUND],
            ['refund', PosInterface::TX_TYPE_REFUND],
            ['Iptal', PosInterface::TX_TYPE_CANCEL],
            ['void', PosInterface::TX_TYPE_CANCEL],
            ['', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['WAITINGPOSTAUTH', PosInterface::TX_TYPE_STATUS, null, PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED],
            ['APPROVED', PosInterface::TX_TYPE_STATUS, null, 'APPROVED'],
            ['blabla', PosInterface::TX_TYPE_STATUS, null, 'blabla'],

            ['Basarili', PosInterface::TX_TYPE_HISTORY, null, 'Basarili'],
            ['blabla', PosInterface::TX_TYPE_HISTORY, null, 'blabla'],

            ['Basarili', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_CANCEL, PosInterface::PAYMENT_STATUS_CANCELED],
            ['Onaylandi', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_CANCEL, PosInterface::PAYMENT_STATUS_CANCELED],
            ['Basarili', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_REFUND, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['Basarili', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_AUTH, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['Basarili', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['Basarili', PosInterface::TX_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED],
            ['Basarili', PosInterface::TX_TYPE_HISTORY, '', 'Basarili'],
            ['Iptal', PosInterface::TX_TYPE_HISTORY, '', 'Iptal'],
            ['', PosInterface::TX_TYPE_HISTORY, '', PosInterface::PAYMENT_STATUS_ERROR],
            ['blabla', '', '', 'blabla'],
        ];
    }


    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3D', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_3D_SECURE],
            ['', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE],
            ['abc', PosInterface::TX_TYPE_HISTORY, null],
            ['3D', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D_PAY', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY],
            ['3D_PAY', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_PAY],
        ];
    }
}
