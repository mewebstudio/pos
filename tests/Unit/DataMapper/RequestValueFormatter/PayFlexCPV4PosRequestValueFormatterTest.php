<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter
 */
class PayFlexCPV4PosRequestValueFormatterTest extends TestCase
{
    private PayFlexCPV4PosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayFlexCPV4PosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(PayFlexCPV4Pos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertFalse($result);
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
     * @testWith [1, "1.00"]
     * [1.1, "1.10"]
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
     * @testWith ["ExpireMonth", "04"]
     * ["ExpireYear", "24"]
     * ["Expiry", "202404"]
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
