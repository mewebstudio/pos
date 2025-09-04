<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\BasicResponseValueFormatter;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\BasicResponseValueFormatter
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\AbstractResponseValueFormatter
 */
class BasicResponseValueFormatterTest extends TestCase
{
    private BasicResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BasicResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(AkbankPos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(PayFlexCPV4Pos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(PayFlexV4Pos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(PayForPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider formatAmountProvider
     */
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatInstallmentProvider
     */
    public function testFormatInstallment(?string $installment, string $txType, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment, $txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatDateTimeProvider
     */
    public function testFormatDateTime(string $dateTime, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, '');
        $this->assertSame($expected, $actual->format('Y-m-d H:i:s'));
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['1.00', PosInterface::TX_TYPE_PAY_AUTH, 1.0],
            ['1.00', '', 1.0],
        ];
    }

    public static function formatInstallmentProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['1', '', 0],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['0', '', 0],
            [null, PosInterface::TX_TYPE_PAY_AUTH, 0],
            [null, '', 0],
        ];
    }

    public static function formatDateTimeProvider(): array
    {
        return [
            // AkbankPos txnDateTime, preAuthCloseDate
            // KuveytPos OrderDate, UpdateSystemDate
            // VakifKatilim OrderDate
            ['2024-04-23T16:14:00.264', '2024-04-23 16:14:00'],

            // EstPos AUTH_DTTM, CAPTURE_DTTM, VOID_DTTM
            // Garanti ProvDate, PreAuthDate
            // PosNet tranDate
            ['2022-10-30 12:29:53.773', '2022-10-30 12:29:53'],

            // Garanti LastTrxDate
            ['2024-06-03 16:06:29', '2024-06-03 16:06:29'],

            // PosNetV1 TransactionDate => '2019-11-0813:58:37.909'
            ['2019-11-0813:58:37.909', '2019-11-08 13:58:37'],

            // Garanti ProvDate
            // EstPos TRXDATE, EXTRA_TRXDATE
            ['20221101 13:14:19', '2022-11-01 13:14:19'],

            // PayFlexCPV4 HostDate
            // ToslaPos CreateDate
            ['20230309221037', '2023-03-09 22:10:37'],

            // InterPos TRXDATE, VoidDate
            // PayForPos InsertDatetime, TransactionDate
            ['09.08.2024 10:40:34', '2024-08-09 10:40:34'],

            // VakifKatilim TransactionTime
            ['2019-08-16T10:54:23.81069', '2019-08-16 10:54:23'],
            ['2024-07-01T13:15:47.2754872+03:00', '2024-07-01 13:15:47'],
        ];
    }
}
