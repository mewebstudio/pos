<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\GarantiPosResponseValueFormatter
 */
class GarantiPosResponseValueFormatterTest extends TestCase
{
    private GarantiPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new GarantiPosResponseValueFormatter();
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

    public static function formatInstallmentProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['1', '', 0],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['0', '', 0],
            [null, PosInterface::TX_TYPE_PAY_AUTH, 0],
            [null, '', 0],
            ['1', PosInterface::TX_TYPE_HISTORY, 0],
            ['Pesin', PosInterface::TX_TYPE_HISTORY, 0],
        ];
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['1001', PosInterface::TX_TYPE_PAY_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_PAY_PRE_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_PAY_POST_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_CANCEL, 10.01],
            ['1001', PosInterface::TX_TYPE_REFUND, 10.01],
            ['1001', PosInterface::TX_TYPE_REFUND_PARTIAL, 10.01],
            ['1001', PosInterface::TX_TYPE_STATUS, 10.01],
            ['1001', PosInterface::TX_TYPE_ORDER_HISTORY, 10.01],
            ['1001', '', 10.01],
        ];
    }
}
