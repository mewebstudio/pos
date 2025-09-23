<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\PosNetResponseValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\PosNetResponseValueFormatter
 */
class PosNetResponseValueFormatterTest extends TestCase
{
    private PosNetResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PosNetResponseValueFormatter();
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
            ['10,1', PosInterface::TX_TYPE_STATUS, 10.1],
            ['1.056,2', PosInterface::TX_TYPE_STATUS, 1056.2],
        ];
    }
}
