<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\PayFlexV4PosRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\PayFlexV4PosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class PayFlexV4PosRequestValueMapperTest extends TestCase
{
    private PayFlexV4PosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new PayFlexV4PosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(PayFlexV4Pos::class);
        $this->assertTrue($result);

        $result = $this->valueMapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->valueMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["Auth"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType($txType);
    }

    public function testMapSecureType(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapSecureType(PosInterface::MODEL_3D_SECURE);
    }

    /**
     * @testWith ["DAY", "Day"]
     * ["MONTH", "Month"]
     * ["YEAR", "Year"]
     */
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapRecurringFrequency($frequency));
    }

    public function testMapLang(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapLang(PosInterface::LANG_TR);
        $this->assertSame('en', $this->valueMapper->mapLang(PosInterface::LANG_EN));
        $this->assertSame('tr', $this->valueMapper->mapLang('ru'));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $this->assertSame('949', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('978', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getLangMappings());
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertCount(3, $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(7, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(4, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH,  'Sale'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'Auth'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, 'Capture'],
        ];
    }
}
