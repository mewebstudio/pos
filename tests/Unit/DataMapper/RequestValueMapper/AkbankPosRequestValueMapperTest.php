<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AkbankPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class AkbankPosRequestValueMapperTest extends TestCase
{
    private AkbankPosRequestValueMapper $valueMapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new AkbankPosRequestValueMapper();
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->valueMapper->mapTxType($txType, $paymentModel);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider mapTxTypeUnsupportedDataProvider
     */
    public function testMapTxTypeException(string $txType, ?string $paymentModel, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->valueMapper->mapTxType($txType, $paymentModel);
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
     * ["YEAR", "Y"]
     */
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapRecurringFrequency($frequency));
    }

    public function testMapLang(): void
    {
        $this->assertSame('TR', $this->valueMapper->mapLang(PosInterface::LANG_TR));
        $this->assertSame('EN', $this->valueMapper->mapLang(PosInterface::LANG_EN));
        $this->assertSame('TR', $this->valueMapper->mapLang('ru'));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $this->assertSame(949, $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame(978, $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getLangMappings());
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertCount(4, $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(8, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings()
    {
        $this->assertCount(4, $this->valueMapper->getSecureTypeMappings());
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
            [PosInterface::MODEL_3D_HOST, '3D_PAY_HOSTING'],
            [PosInterface::MODEL_NON_SECURE, 'PAY_HOSTING'],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, '3000'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE, '3004'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_NON_SECURE, '1004'],
        ];
    }

    public static function mapTxTypeUnsupportedDataProvider(): array
    {
        return [
            ['3000', null, UnsupportedTransactionTypeException::class],
            [PosInterface::TX_TYPE_PAY_AUTH, null, \InvalidArgumentException::class],
        ];
    }
}
