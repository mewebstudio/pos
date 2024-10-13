<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\PosNetRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\PosNetRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class PosNetRequestValueMapperTest extends TestCase
{
    private PosNetRequestValueMapper $valueMapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new PosNetRequestValueMapper();
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
     * @testWith ["Sale"]
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

    public function testMapRecurringFrequency(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapRecurringFrequency('DAY');
    }

    public function testMapLang(): void
    {
        $this->assertSame('tr', $this->valueMapper->mapLang(PosInterface::LANG_TR));
        $this->assertSame('en', $this->valueMapper->mapLang(PosInterface::LANG_EN));
        $this->assertSame('tr', $this->valueMapper->mapLang('ru'));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $this->assertSame('TL', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('EU', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getLangMappings());
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(7, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings()
    {
        $this->assertCount(0, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH,  'Sale'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'Auth'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, 'Capt'],
        ];
    }
}
