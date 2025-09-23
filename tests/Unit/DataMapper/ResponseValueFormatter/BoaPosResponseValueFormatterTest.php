<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\BoaPosResponseValueFormatter
 */
class BoaPosResponseValueFormatterTest extends TestCase
{
    private BoaPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BoaPosResponseValueFormatter();
    }

    /**
     * @dataProvider formatAmountProvider
     */
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['101', '', 1.01],
            ['101', PosInterface::TX_TYPE_PAY_AUTH, 1.01],
            ['101', PosInterface::TX_TYPE_STATUS, 101],
            ['101', PosInterface::TX_TYPE_HISTORY, 101],
            ['101', PosInterface::TX_TYPE_ORDER_HISTORY, 101],
        ];
    }
}
