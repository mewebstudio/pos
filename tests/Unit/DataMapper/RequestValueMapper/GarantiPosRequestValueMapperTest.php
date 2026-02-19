<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\GarantiPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class GarantiPosRequestValueMapperTest extends TestCase
{
    private GarantiPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new GarantiPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(GarantiPos::class);
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
     * @testWith ["sales"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType($txType);
    }

    /**
     * @dataProvider mapSecureTypeDataProvider
     */
    public function testMapSecureType(string $paymentModel, string $expected): void
    {
        $mappedSecureType = $this->valueMapper->mapSecureType($paymentModel);
        $this->assertSame($expected, $mappedSecureType);
    }

    /**
     * @testWith ["DAY", "D"]
     * ["WEEK", "W"]
     * ["MONTH", "M"]
     */
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapRecurringFrequency($frequency));
    }

    public function testMapLang(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapLang(PosInterface::LANG_TR);
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
        $this->assertCount(9, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [PosInterface::MODEL_3D_SECURE, '3D'],
            [PosInterface::MODEL_3D_PAY, '3D_PAY'],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH,  'sales'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'preauth'],
        ];
    }
}
