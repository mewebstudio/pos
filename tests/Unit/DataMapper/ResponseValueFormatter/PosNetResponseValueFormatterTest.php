<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\PosNetResponseValueFormatter;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
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

    public function testSupports(): void
    {
        $result = $this->formatter::supports(PosNet::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(PosNetV1Pos::class);
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

    public static function formatAmountProvider(): array
    {
        return [
            ['101', '', 1.01],
            ['10,1', PosInterface::TX_TYPE_STATUS, 10.1],
            ['1.056,2', PosInterface::TX_TYPE_STATUS, 1056.2],
        ];
    }
}
