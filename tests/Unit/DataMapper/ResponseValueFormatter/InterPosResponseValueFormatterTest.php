<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\InterPosResponseValueFormatter
 */
class InterPosResponseValueFormatterTest extends TestCase
{
    private InterPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new InterPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(InterPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertFalse($result);
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
            ['0', '', 0.0],
            ['1.056,2', '', 1056.2],
            ['1,01', '', 1.01],
        ];
    }
}
