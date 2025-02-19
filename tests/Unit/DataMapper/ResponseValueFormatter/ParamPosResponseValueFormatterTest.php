<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\ParamPosResponseValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\ParamPosResponseValueFormatter
 */
class ParamPosResponseValueFormatterTest extends TestCase
{
    private ParamPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ParamPosResponseValueFormatter();
    }

    /**
     * @dataProvider formatAmountProvider
     */
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatInstallmentProvider
     */
    public function testFormatInstallment(?string $installment, string $txType, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['1.01', '', 1.01],
            ['1.01', PosInterface::TX_TYPE_PAY_AUTH, 1.01],
            ['101', PosInterface::TX_TYPE_STATUS, 101],
        ];
    }

    public static function formatInstallmentProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['1', '', 0],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['0', '', 0],
            [null, PosInterface::TX_TYPE_PAY_AUTH, 0],
            [null, '', 0],
        ];
    }

    /**
     * @dataProvider formatDateTimeProvider
     */
    public function testFormatDateTime(string $dateTime, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, '');
        $this->assertSame($expected, $actual->format('Y-m-d H:i:s'));
    }

    public static function formatDateTimeProvider(): array
    {
        return [
            'TURKPOS_RETVAL_Islem_Tarih' => ['19.01.2025 18:53:48', '2025-01-19 18:53:48'],
            'Tarih'                      => ['05.01.2025 13:14:32', '2025-01-05 13:14:32'],
        ];
    }
}
