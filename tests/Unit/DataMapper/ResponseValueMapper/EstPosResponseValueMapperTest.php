<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\EstPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\EstPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class EstPosResponseValueMapperTest extends TestCase
{
    private EstPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            EstV3Pos::class,
            RequestValueMapperFactory::createForGateway(EstV3Pos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(EstPos::class);
        $this->assertTrue($result);
        $result = $this->mapper::supports(EstV3Pos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AkbankPos::class);
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
            ['S', PosInterface::TX_TYPE_PAY_AUTH],
            ['C', PosInterface::TX_TYPE_REFUND],
            ['', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['D', PosInterface::PAYMENT_STATUS_ERROR],
            ['ERR', PosInterface::PAYMENT_STATUS_ERROR],
            ['A', PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['C', PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['S', PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['PN', PosInterface::PAYMENT_STATUS_PAYMENT_PENDING],
            ['CNCL', PosInterface::PAYMENT_STATUS_CANCELED],
            ['V', PosInterface::PAYMENT_STATUS_CANCELED],
            ['blabla', 'blabla'],
        ];
    }


    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3d', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3d', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3d', '', PosInterface::MODEL_3D_SECURE],
            ['3d_pay', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY],
            ['3d_pay', '', PosInterface::MODEL_3D_PAY],
            ['3d_pay_hosting', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY_HOSTING],
            ['3d_host', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_HOST],
            ['regular', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE],
            ['regular', PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::MODEL_NON_SECURE],
        ];
    }

}
