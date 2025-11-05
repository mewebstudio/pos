<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\ToslaPos;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\ToslaPosRequestValueFormatter
 */
class ToslaPosRequestValueFormatterTest extends TestCase
{
    private ToslaPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ToslaPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(ToslaPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }


    /**
     * @testWith [0, 0]
     *            [1, 0]
     *            [2, 2]
     */
    public function testFormatInstallment(int $installment, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith [1.1, 110]
     * [1, 100]
     */
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["ExpireDate", "04/24"]
     * ["expireDate", "0424"]
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

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(?string $fieldName, string $expected): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["abc"]
     * [null]
     * [""]
     */
    public function testFormatDateTimeUnsupportedField(?string $fieldName): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatDateTime($dateTime, $fieldName);
    }

    public static function formatDateTimeDataProvider(): array
    {
        return [
            [
                'timeSpan',
                '20240414164530',
            ],
            [
                'transactionDate',
                '20240414',
            ],
        ];
    }
}
