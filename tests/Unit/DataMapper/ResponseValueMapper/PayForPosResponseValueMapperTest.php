<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\PayForPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\PayForPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class PayForPosResponseValueMapperTest extends TestCase
{
    private PayForPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            PayForPos::class,
            RequestValueMapperFactory::createForGateway(PayForPos::class)
        );
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
            ['Auth', PosInterface::TX_TYPE_PAY_AUTH],
            ['PreAuth', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['PostAuth', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['Void', PosInterface::TX_TYPE_CANCEL],
            ['Refund', PosInterface::TX_TYPE_REFUND],
            ['TxnHistory', PosInterface::TX_TYPE_HISTORY],
            ['OrderInquiry', PosInterface::TX_TYPE_STATUS],
            ['blabla', null],
        ];
    }


    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3DModel', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3DModel', '', PosInterface::MODEL_3D_SECURE],
            ['3DPay', '', PosInterface::MODEL_3D_PAY],
            ['3DHost', '', PosInterface::MODEL_3D_HOST],
            ['NonSecure', '', PosInterface::MODEL_NON_SECURE],
            ['blabla', '', null],
        ];
    }
}
