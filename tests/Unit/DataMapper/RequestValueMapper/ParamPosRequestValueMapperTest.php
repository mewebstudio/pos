<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueMapper;

use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper
 * @covers \Mews\Pos\DataMapper\RequestValueMapper\AbstractRequestValueMapper
 */
class ParamPosRequestValueMapperTest extends TestCase
{
    private ParamPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new ParamPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(ParamPos::class);
        $this->assertTrue($result);
        $result = $this->valueMapper::supports(Param3DHostPos::class);
        $this->assertTrue($result);

        $result = $this->valueMapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, string $paymentModel, ?array $order, string $expected): void
    {
        $actual = $this->valueMapper->mapTxType($txType, $paymentModel, $order);
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
        $this->assertSame('1000', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('1001', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_USD));
        $this->assertSame('1002', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
        $this->assertSame('1003', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_GBP));
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
        $this->assertCount(4, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(8, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(3, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [PosInterface::MODEL_3D_SECURE, '3D'],
            [PosInterface::MODEL_3D_PAY, '3D'],
            [PosInterface::MODEL_NON_SECURE, 'NS'],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, ['currency' => PosInterface::CURRENCY_USD], 'TP_Islem_Odeme_WD'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, ['currency' => PosInterface::CURRENCY_USD], 'TP_Islem_Odeme_WD'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, ['currency' => PosInterface::CURRENCY_TRY], 'TP_WMD_UCD'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, null, 'TP_WMD_UCD'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, null, 'TP_WMD_UCD'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY, null, 'Pos_Odeme'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_HOST, null, 'TO_Pre_Encrypting_OOS'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Odeme_OnProv_WMD'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE, null, 'TP_Islem_Odeme_OnProv_WMD'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Odeme_OnProv_Kapa'],
            [PosInterface::TX_TYPE_REFUND, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Iptal_Iade_Kismi2'],
            [PosInterface::TX_TYPE_REFUND_PARTIAL, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Iptal_Iade_Kismi2'],
            [PosInterface::TX_TYPE_CANCEL, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Iptal_Iade_Kismi2'],
            [PosInterface::TX_TYPE_CANCEL, PosInterface::MODEL_3D_SECURE, ['transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH], 'TP_Islem_Iptal_OnProv'],
            [PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Sorgulama4'],
            [PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE, null, 'TP_Islem_Izleme'],
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
