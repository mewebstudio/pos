<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\VakifKatilimPosRequestValueFormatter
 */
class VakifKatilimPosRequestValueFormatterTest extends TestCase
{
    private VakifKatilimPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new VakifKatilimPosRequestValueFormatter();
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

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(\DateTimeInterface $dateTime, ?string $fieldName, ?string $txType, string $expected): void
    {
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
                new \DateTime('2024-04-14T16:45:30.000'),
                'StartDate',
                PosInterface::TX_TYPE_HISTORY,
                '2024-04-14',
            ],
            [
                new \DateTime('2024-04-14T16:45:30.000'),
                'EndDate',
                PosInterface::TX_TYPE_HISTORY,
                '2024-04-14',
            ],
        ];
    }
}
