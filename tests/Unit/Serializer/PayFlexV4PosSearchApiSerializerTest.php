<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\PayFlexV4PosSearchApiSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Serializer\PayFlexV4PosSearchApiSerializer
 */
class PayFlexV4PosSearchApiSerializerTest extends TestCase
{
    private PayFlexV4PosSearchApiSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PayFlexV4PosSearchApiSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_QUERY_API);
        $this->assertTrue($supports);

        $supports = $this->serializer::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_PAYMENT_API);
        $this->assertFalse($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $txType, ?string $format, string $expectedFormat, $expected): void
    {
        $result   = $this->serializer->encode($data, $txType, $format);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $result->getData());
        $this->assertSame($expectedFormat, $result->getFormat());
    }

    /**
     * @testWith ["pay"]
     * @testWith ["pre"]
     * @testWith ["post"]
     */
    public function testEncodeException(string $txType): void
    {
        $data = ['abc' => 1];

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode($data, $txType);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, array $expected): void
    {
        $actual = $this->serializer->decode($input);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider decodeExceptionDataProvider
     */
    public function testDecodeException(string $input, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);

        $this->serializer->decode($input);
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

    public static function decodeExceptionDataProvider(): Generator
    {
        yield 'test1' => [
            'input'                    => "<html><head><title>Request Rejected</title></head><body>The requested URL was rejected. Please consult with your administrator.<br><br>Your support ID is: 11795445874629392419<br><br><a href='javascript:history.back();'>[Go Back]</a></body></html>",
            'expected_exception_class' => \Exception::class,
        ];
        yield 'test2' => [
            'input'                    => '',
            'expected_exception_class' => NotEncodableValueException::class,
        ];
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test_status' => [
            'input'           => [
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
            'txType'          => PosInterface::TX_TYPE_STATUS,
            'format'          => SerializerInterface::FORMAT_XML,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<?xml version="1.0" encoding="UTF-8"?>
<SearchRequest><MerchantCriteria><HostMerchantId>000000000111111</HostMerchantId><MerchantPassword>3XTgER89as</MerchantPassword></MerchantCriteria><TransactionCriteria><TransactionId></TransactionId><OrderId>order222</OrderId><AuthCode></AuthCode></TransactionCriteria></SearchRequest>
',
        ];
    }

}
