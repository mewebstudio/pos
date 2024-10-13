<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\EstPosRequestValueFormatter;
use Mews\Pos\Exceptions\NotImplementedException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\EstPosRequestValueFormatter
 */
class EstPosRequestValueFormatterTest extends TestCase
{
    private EstPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new EstPosRequestValueFormatter();
    }

    /**
     * @testWith [0, ""]
     *            [1, ""]
     *            [2, "2"]
     */
    public function testFormatInstallment($installment, string $expected): void
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
     * @testWith ["abc"]
     * [""]
     */
    public function testFormatCreditCardExpDateUnSupportedField(string $fieldName): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, $fieldName);
    }

    /**
     * @testWith ["Ecom_Payment_Card_ExpDate_Month", "04"]
     * ["Ecom_Payment_Card_ExpDate_Year", "24"]
     * ["Expires", "04/24"]
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
        $this->expectException(NotImplementedException::class);
        $this->formatter->formatDateTime($dateTime);
    }
}
