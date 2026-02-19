<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\KuveytPosSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Serializer\KuveytPosSerializer
 */
class KuveytPosSerializerTest extends TestCase
{
    private KuveytPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new KuveytPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(KuveytPos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $txType, ?string $format, string $expectedFormat, $expected): void
    {
        $result = $this->serializer->encode($data, $txType, $format);
        if (is_string($expected)) {
            $expected = str_replace(["\r"], '', $expected);
        }

        $this->assertSame($expected, $result->getData());
        $this->assertSame($expectedFormat, $result->getFormat());
    }

    /**
     * @dataProvider decodeXmlDataProvider
     */
    public function testDecodeXML(string $input, string $txType, array $expected): void
    {
        $actual = $this->serializer->decode($input, $txType);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider decodeExceptionDataProvider
     */
    public function testDecodeException(string $input, string $txType, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);

        $this->serializer->decode($input, $txType);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test_pay' => [
            'input'           => ['abc' => 1],
            'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><abc>1</abc></KuveytTurkVPosMessage>
',
        ];

        yield 'test_pay_2' => [
            'input'           => ['abc' => 1],
            'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
            'format'          => SerializerInterface::FORMAT_XML,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><abc>1</abc></KuveytTurkVPosMessage>
',
        ];
    }

    public static function decodeXmlDataProvider(): iterable
    {
        yield [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>ğĞüÜşŞiİöÖÇçüÜ</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'VPosMessage'          => [
                    'APIVersion'          => '1.0.0',
                    'OkUrl'               => 'http://localhost:44785/Home/Success',
                    'FailUrl'             => 'http://localhost:44785/Home/Fail',
                    'HashData'            => 'lYJYMi/gVO9MWr32Pshaa/zAbSHY=',
                    'MerchantId'          => '80',
                    'SubMerchantId'       => '0',
                    'CustomerId'          => '400235',
                    'UserName'            => 'apiuser',
                    'CardNumber'          => '4025502306586032',
                    'CardHolderName'      => 'ğĞüÜşŞiİöÖÇçüÜ',
                    'CardType'            => 'MasterCard',
                    'BatchID'             => '0',
                    'TransactionType'     => 'Sale',
                    'InstallmentCount'    => '0',
                    'Amount'              => '100',
                    'DisplayAmount'       => '100',
                    'MerchantOrderId'     => 'Order 123',
                    'FECAmount'           => '0',
                    'CurrencyCode'        => '0949',
                    'QeryId'              => '0',
                    'DebtId'              => '0',
                    'SurchargeAmount'     => '0',
                    'SGKDebtAmount'       => '0',
                    'TransactionSecurity' => '3',
                    'TransactionSide'     => 'Auto',
                    'EntryGateMethod'     => 'VPOS_ThreeDModelPayGate',
                ],
                'IsEnrolled'           => 'true',
                'IsVirtual'            => 'false',
                'OrderId'              => '0',
                'TransactionTime'      => '0001-01-01T00:00:00',
                'ResponseCode'         => '00',
                'ResponseMessage'      => 'HATATA',
                'MD'                   => '67YtBfBRTZ0XBKnAHi8c/A==',
                'AuthenticationPacket' => 'WYGDgSIrSHDtYwF/WEN+nfwX63sppA=',
                'ACSURL'               => 'https://acs.bkm.com.tr/mdpayacs/pareq',
            ],
        ];
    }

    public static function decodeExceptionDataProvider(): Generator
    {
        yield 'test1' => [
            'input'                    => '',
            'txType'                   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected_exception_class' => NotEncodableValueException::class,
        ];
    }
}
