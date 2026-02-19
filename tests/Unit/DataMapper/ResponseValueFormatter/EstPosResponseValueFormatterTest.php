<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\EstPosResponseValueFormatter;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\EstPosResponseValueFormatter
 */
class EstPosResponseValueFormatterTest extends TestCase
{
    private EstPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new EstPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(EstPos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AkbankPos::class);
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
            ['1.00', PosInterface::TX_TYPE_PAY_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_PAY_PRE_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_PAY_POST_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_CANCEL, 1.0],
            ['1.00', PosInterface::TX_TYPE_REFUND, 1.0],
            ['1.00', PosInterface::TX_TYPE_REFUND_PARTIAL, 1.0],
            ['1.00', '', 1.0],
            ['1001', PosInterface::TX_TYPE_STATUS, 10.01],
            ['1001', PosInterface::TX_TYPE_ORDER_HISTORY, 10.01],
        ];
    }
}
