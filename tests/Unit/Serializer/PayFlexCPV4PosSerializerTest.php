<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\PayFlexCPV4PosSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Serializer\PayFlexCPV4PosSerializer
 */
class PayFlexCPV4PosSerializerTest extends TestCase
{
    private PayFlexCPV4PosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PayFlexCPV4PosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PayFlexCPV4Pos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider decodeExceptionDataProvider
     */
    public function testDecodeException(string $input, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);

        $this->serializer->decode($input);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $txType, array $expected): void
    {
        $result = $this->serializer->encode($data, $txType);

        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider encodeNonPaymentDataProvider
     */
    public function testEncodeNonPayment(array $data, string $txType, string $expected): void
    {
        $result = $this->serializer->encode($data, $txType);

        $this->assertSame($expected, $result);
    }

    public function testEncodeException(): void
    {
        $data = ['abc' => 1];

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode($data, PosInterface::TX_TYPE_HISTORY);

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode($data, PosInterface::TX_TYPE_ORDER_HISTORY);

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode($data, PosInterface::TX_TYPE_STATUS);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input' => [
                'MerchantId' => '000000000111111',
                'Password' => '3XTgER89as',
                'TransactionType' => 'Sale',
                'OrderId' => 'order222',
                'CurrencyAmount' => '100.00',
                'CurrencyCode' => '949',
                'ClientIp' => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'Pan' => '5555444433332222',
                'Expiry' => '202112',
                'Cvv' => '122',
            ],
            'txType' => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'MerchantId' => '000000000111111',
                'Password' => '3XTgER89as',
                'TransactionType' => 'Sale',
                'OrderId' => 'order222',
                'CurrencyAmount' => '100.00',
                'CurrencyCode' => '949',
                'ClientIp' => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'Pan' => '5555444433332222',
                'Expiry' => '202112',
                'Cvv' => '122',
            ],
        ];
    }

    public static function encodeNonPaymentDataProvider(): Generator
    {
        yield 'test1' => [
            'input' => [
                'MerchantId' => '000000000111111',
                'Password' => '3XTgER89as',
                'TransactionType' => 'Sale',
                'OrderId' => 'order222',
                'CurrencyAmount' => '100.00',
                'CurrencyCode' => '949',
                'ClientIp' => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'Pan' => '5555444433332222',
                'Expiry' => '202112',
                'Cvv' => '122',
            ],
            'txType' => PosInterface::TX_TYPE_CANCEL,
            'expected' => '<VposRequest><MerchantId>000000000111111</MerchantId><Password>3XTgER89as</Password><TransactionType>Sale</TransactionType><OrderId>order222</OrderId><CurrencyAmount>100.00</CurrencyAmount><CurrencyCode>949</CurrencyCode><ClientIp>127.0.0.1</ClientIp><TransactionDeviceSource>0</TransactionDeviceSource><Pan>5555444433332222</Pan><Expiry>202112</Expiry><Cvv>122</Cvv></VposRequest>',
        ];
    }

    public static function decodeExceptionDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => "<html><head><title>Request Rejected</title></head><body>The requested URL was rejected. Please consult with your administrator.<br><br>Your support ID is: 11795445874629392419<br><br><a href='javascript:history.back();'>[Go Back]</a></body></html>",
            'expected_exception_class' => \Exception::class,
        ];
        yield 'test2' => [
            'input'    => '',
            'expected_exception_class' => NotEncodableValueException::class,
        ];
    }
}
