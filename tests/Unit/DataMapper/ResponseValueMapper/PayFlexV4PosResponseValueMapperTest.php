<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexV4PosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\PayFlexV4PosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class PayFlexV4PosResponseValueMapperTest extends TestCase
{
    private PayFlexV4PosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            PayFlexV4Pos::class,
            RequestValueMapperFactory::createForGateway(PayFlexV4Pos::class)
        );
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(PayFlexV4Pos::class);
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
            ['status', PosInterface::TX_TYPE_STATUS],
            ['', null],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE],
            ['2', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY],
            ['1', '', PosInterface::MODEL_NON_SECURE],
            ['2', '', PosInterface::MODEL_3D_SECURE],
            ['3', '', PosInterface::MODEL_3D_PAY],
            ['4', '', null],
        ];
    }
}
