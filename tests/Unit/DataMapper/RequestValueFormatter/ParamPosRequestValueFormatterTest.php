<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter
 */
class ParamPosRequestValueFormatterTest extends TestCase
{
    private ParamPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ParamPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(ParamPos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(Param3DHostPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @testWith [0, "1"]
     *            [1, "1"]
     *            [2, "2"]
     */
    public function testFormatInstallment(int $installment, string $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatAmountDataProvider
     */
    public function testFormatAmount(float $amount, string $txType, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["KK_SK_Yil", "2024"]
     * ["KK_SK_Ay", "04"]
     */
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["abc"]
     * [""]
     */
    public function testFormatCreditCardExpDateUnSupportedField(string $fieldName): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, $fieldName);
    }

    public function testFormatDateTime(): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime);
        $this->assertSame('14.04.2024 16:45:30', $actual);
    }

    public static function formatAmountDataProvider(): array
    {
        return [
            [1.0, PosInterface::TX_TYPE_PAY_AUTH, '1,00'],
            [1000.0, PosInterface::TX_TYPE_PAY_AUTH, '1000,00'],
            [1.0, PosInterface::TX_TYPE_CANCEL, '1.00'],
            [1000.0, PosInterface::TX_TYPE_CANCEL, '1000.00'],
            [1.0, PosInterface::TX_TYPE_REFUND, '1.00'],
            [1000.0, PosInterface::TX_TYPE_REFUND, '1000.00'],
            [1.0, PosInterface::TX_TYPE_REFUND_PARTIAL, '1.00'],
            [1000.0, PosInterface::TX_TYPE_REFUND_PARTIAL, '1000.00'],
        ];
    }
}
