<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter
 */
class KuveytPosRequestValueFormatterTest extends TestCase
{
    private KuveytPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new KuveytPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(KuveytPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(KuveytSoapApiPos::class);
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
     * @testWith [1.1, 110]
     * [1.0, 100]
     */
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["CardExpireDateMonth", "04"]
     * ["CardExpireDateYear", "24"]
     */
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);
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
        $this->assertSame('2024-04-14T16:45:30', $actual);
    }
}
