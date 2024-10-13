<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\PosNetRequestValueFormatter;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\PosNetRequestValueFormatter
 */
class PosNetRequestValueFormatterTest extends TestCase
{
    private PosNetRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PosNetRequestValueFormatter();
    }

    /**
     * @testWith [0, "00"]
     *            [1, "00"]
     *            [2, "02"]
     *            [12, "12"]
     */
    public function testFormatInstallment($installment, string $expected): void
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
     * @dataProvider formatOrderIdDataProvider
     */
    public function testFormatOrderId(string $orderId, ?string $txType, ?string $orderPaymentModel, string $expected): void
    {
        $actual = $this->formatter->formatOrderId($orderId, $txType, $orderPaymentModel);
        $this->assertSame($expected, $actual);
    }

    public function testFormatOrderIdFail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatOrderId('1234567890123456789AB');
    }

    /**
     * @testWith ["Expiry", "2404"]
     * ["", "2404"]
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

    public static function formatOrderIdDataProvider(): array
    {
        return [
            ['ABC123', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, '00000000000000ABC123'],
            ['ABC123', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, '00000000000000ABC123'],
            ['ABC123', PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_3D_SECURE, 'TDSC00000000000000ABC123'],
            ['ABC123', PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE, '000000000000000000ABC123'],
            ['ABC123', PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_3D_PAY, '000000000000000000ABC123'],
        ];
    }
}
