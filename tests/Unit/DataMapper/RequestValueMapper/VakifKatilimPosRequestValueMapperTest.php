<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\VakifKatilimPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class VakifKatilimPosRequestValueMapperTest extends TestCase
{
    private VakifKatilimPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new VakifKatilimPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(VakifKatilimPos::class);
        $this->assertTrue($result);

        $result = $this->valueMapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    public function testMapTxType(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider mapSecureTypeDataProvider
     */
    public function testMapSecureType(string $paymentModel, string $expected): void
    {
        $mappedSecureType = $this->valueMapper->mapSecureType($paymentModel);
        $this->assertSame($expected, $mappedSecureType);
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
        $this->assertSame('0949', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('0978', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
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
        $this->assertCount(0, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getCardTypeMappings());
    }

    public function testMapCardType(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapCardType(CreditCardInterface::CARD_TYPE_VISA);
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [PosInterface::MODEL_3D_SECURE, '3'],
            [PosInterface::MODEL_NON_SECURE, '5'],
        ];
    }
}
