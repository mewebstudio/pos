<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter
 */
class PayForPosRequestValueFormatterTest extends TestCase
{
    private PayForPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayForPosRequestValueFormatter();
    }

    /**
     * @testWith [0, "0"]
     *            [1, "0"]
     *            [2, "2"]
     */
    public function testFormatInstallment(int $installment, string $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith [1.1, "1.1"]
     * [1.0, "1"]
     */
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["", "0424"]
     * ["abc", "0424"]
     */
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    public function testFormatDateTime(): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime);
        $this->assertSame('20240414', $actual);
    }
}
