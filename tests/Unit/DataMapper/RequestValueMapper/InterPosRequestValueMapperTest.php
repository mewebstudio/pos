<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\InterPosRequestValueMapper;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\InterPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class InterPosRequestValueMapperTest extends TestCase
{
    private InterPosRequestValueMapper $valueMapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new InterPosRequestValueMapper();
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

    public function testMapRecurringFrequency(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapRecurringFrequency('DAY');
    }

    public function testMapLang(): void
    {
        $this->assertSame('tr', $this->valueMapper->mapLang(PosInterface::LANG_TR));
    }

    public function testMapCurrency(): void
    {
        $this->assertSame('949', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('978', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    /**
     * @dataProvider mapCardTypeDataProvider
     */
    public function testMapCardType(string $cardType, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapCardType($cardType));
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
        $this->assertCount(4, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(4, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [PosInterface::MODEL_3D_SECURE, '3DModel'],
            [PosInterface::MODEL_3D_PAY, '3DPay'],
            [PosInterface::MODEL_NON_SECURE, 'NonSecure'],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, 'Auth'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'PreAuth'],
        ];
    }

    public static function mapCardTypeDataProvider(): array
    {
        return [
            [
                CreditCardInterface::CARD_TYPE_VISA,
                '0',
            ],
            [
                CreditCardInterface::CARD_TYPE_MASTERCARD,
                '1',
            ],
        ];
    }
}
