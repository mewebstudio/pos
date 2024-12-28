<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter
 */
class GarantiPosRequestValueFormatterTest extends TestCase
{
    private GarantiPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new GarantiPosRequestValueFormatter();
    }

    /**
     * @testWith [0, ""]
     *            [1, ""]
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
     * @testWith ["cardexpiredatemonth", "04"]
     * ["cardexpiredateyear", "24"]
     * ["ExpireDate", "0424"]
     */
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(\DateTimeInterface $dateTime, ?string $fieldName, ?string $txType, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatDateTimeDataProvider(): array
    {
        return [
            [
                new \DateTime('2024-04-14T16:45:30.000'),
                'StartDate',
                PosInterface::TX_TYPE_HISTORY,
                '14/04/2024 16:45',
            ],
            [
                new \DateTime('2024-04-14T16:45:30.000'),
                'EndDate',
                PosInterface::TX_TYPE_HISTORY,
                '14/04/2024 16:45',
            ],
            [
                new \DateTime('2024-04-14T16:45:30.000'),
                null,
                null,
                '14/04/2024 16:45',
            ],
        ];
    }
}
