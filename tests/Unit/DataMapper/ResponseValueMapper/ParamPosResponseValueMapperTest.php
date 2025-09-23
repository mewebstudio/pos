<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\ParamPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class ParamPosResponseValueMapperTest extends TestCase
{
    private ParamPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = ResponseValueMapperFactory::createForGateway(
            ParamPos::class,
            RequestValueMapperFactory::createForGateway(ParamPos::class)
        );
    }

    public function testMapTxType(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapTxType('Auth');
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
    public function testMapCurrency(string $currency, string $txType, ?string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->mapper->mapCurrency($currency, $txType)
        );
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
            ['TL', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['TL', '', PosInterface::CURRENCY_TRY],
            ['TRL', '', PosInterface::CURRENCY_TRY],
            ['EUR', '', PosInterface::CURRENCY_EUR],
            ['USD', '', PosInterface::CURRENCY_USD],
            ['949', '', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['SUCCESS', null, false, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['FAIL', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['BANK_FAIL', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['CANCEL', null, false, PosInterface::PAYMENT_STATUS_CANCELED],
            ['REFUND', null, false, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['PARTIAL_REFUND', null, false, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            ['blabla', null, true, 'blabla'],
            ['blabla', null, false, 'blabla'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3D', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_3D_SECURE],
            ['NONSECURE', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE],
            ['abc', PosInterface::TX_TYPE_HISTORY, null],
            ['3D', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
        ];
    }
}
