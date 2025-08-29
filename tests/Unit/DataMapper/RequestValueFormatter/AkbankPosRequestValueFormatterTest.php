<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\AkbankPosRequestValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\AkbankPosRequestValueFormatter
 */
class AkbankPosRequestValueFormatterTest extends TestCase
{
    private AkbankPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new AkbankPosRequestValueFormatter();
    }

    /**
     * @testWith [0, 1]
     *            [1, 1]
     *            [2, 2]
     */
    public function testFormatInstallment(int $installment, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith [1.1, "1.10"]
     * @testWith [1.0, "1.00"]
     * @testWith [1, "1.00"]
     * @testWith [1000.0, "1000.00"]
     */
    public function testFormatAmount(float $amount, string $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["expiredDate", "0424"]
     * ["expireDate", "0424"]
     * ["", "0424"]
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
        $this->assertSame('2024-04-14T16:45:30.000', $actual);
    }
}
