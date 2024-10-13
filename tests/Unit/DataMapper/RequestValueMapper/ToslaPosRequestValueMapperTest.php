<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\ToslaPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class ToslaPosRequestValueMapperTest extends TestCase
{
    private ToslaPosRequestValueMapper $valueMapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new ToslaPosRequestValueMapper();
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
     * @testWith ["1"]
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
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapLang(PosInterface::LANG_TR);
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
        $this->assertCount(0, $this->valueMapper->getLangMappings());
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
        $this->assertCount(6, $this->valueMapper->getTxTypeMappings());
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
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, '1'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE, '2'],
        ];
    }
}
