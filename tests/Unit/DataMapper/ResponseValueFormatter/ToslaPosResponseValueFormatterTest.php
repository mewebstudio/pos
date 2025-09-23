<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\ToslaPosResponseValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\ToslaPosResponseValueFormatter
 */
class ToslaPosResponseValueFormatterTest extends TestCase
{
    private ToslaPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ToslaPosResponseValueFormatter();
    }

    /**
     * @dataProvider formatAmountProvider
     */
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    public function testFormatInstallment(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->formatter->formatInstallment("2", PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['1001', '', 10.01],
        ];
    }
}
