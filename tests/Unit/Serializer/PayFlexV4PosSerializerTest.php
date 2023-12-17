<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use DomainException;
use Generator;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\PayFlexV4PosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\PayFlexV4PosSerializer
 */
class PayFlexV4PosSerializerTest extends TestCase
{
    private PayFlexV4PosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PayFlexV4PosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PayFlexV4Pos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $txType, string $expected)
    {
        $result   = $this->serializer->encode($data, $txType);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $result);
    }

    public function testEncodeException()
    {
        $data = ['abc' => 1];

        $this->expectException(DomainException::class);
        $this->serializer->encode($data, PosInterface::TX_TYPE_HISTORY);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, array $expected)
    {
        $actual = $this->serializer->decode($input);

        $this->assertSame($expected, $actual);
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => '<?xml version="1.0" encoding="utf-8"?><VposResponse><ResultCode>9039</ResultCode><ResultDetail>Üye işyeri bulunamadı.</ResultDetail><InstallmentTable /></VposResponse>',
            'expected' => [
                'ResultCode'       => '9039',
                'ResultDetail'     => 'Üye işyeri bulunamadı.',
                'InstallmentTable' => '',
            ],
        ];
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => [
                'MerchantId'              => '000000000111111',
                'Password'                => '3XTgER89as',
                'TerminalNo'              => 'VP999999',
                'TransactionType'         => 'Sale',
                'OrderId'                 => 'order222',
                'CurrencyAmount'          => '100.00',
                'CurrencyCode'            => '949',
                'ClientIp'                => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'Pan'                     => '5555444433332222',
                'Expiry'                  => '202112',
                'Cvv'                     => '122',
            ],
            'txType'   => PosInterface::TX_TYPE_PAY,
            'expected' => '<VposRequest><MerchantId>000000000111111</MerchantId><Password>3XTgER89as</Password><TerminalNo>VP999999</TerminalNo><TransactionType>Sale</TransactionType><OrderId>order222</OrderId><CurrencyAmount>100.00</CurrencyAmount><CurrencyCode>949</CurrencyCode><ClientIp>127.0.0.1</ClientIp><TransactionDeviceSource>0</TransactionDeviceSource><Pan>5555444433332222</Pan><Expiry>202112</Expiry><Cvv>122</Cvv></VposRequest>',
        ];

        yield 'test_status' => [
            'input'    => [
                'MerchantCriteria'    => [
                    'HostMerchantId'   => '000000000111111',
                    'MerchantPassword' => '3XTgER89as',
                ],
                'TransactionCriteria' => [
                    'TransactionId' => '',
                    'OrderId'       => 'order222',
                    'AuthCode'      => '',
                ],
            ],
            'txType'   => PosInterface::TX_TYPE_STATUS,
            'expected' => '<?xml version="1.0" encoding="UTF-8"?>
<SearchRequest><MerchantCriteria><HostMerchantId>000000000111111</HostMerchantId><MerchantPassword>3XTgER89as</MerchantPassword></MerchantCriteria><TransactionCriteria><TransactionId></TransactionId><OrderId>order222</OrderId><AuthCode></AuthCode></TransactionCriteria></SearchRequest>
',
        ];
    }

}
