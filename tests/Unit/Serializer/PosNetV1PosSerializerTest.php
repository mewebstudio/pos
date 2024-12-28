<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Serializer\PosNetV1PosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\PosNetV1PosSerializer
 */
class PosNetV1PosSerializerTest extends TestCase
{
    private PosNetV1PosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PosNetV1PosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PosNetV1Pos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expected): void
    {
        $result = $this->serializer->encode($data);

        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, array $expected): void
    {
        $actual = $this->serializer->decode($input);

        $this->assertSame($expected, $actual);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input' => [
                'ApiType' => 'JSON',
                'ApiVersion' => 'V100',
                'MACParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'MerchantNo' => '6700950031',
                'TerminalNo' => '67540050',
                'CipheredData' => null,
                'DealerData' => null,
                'IsEncrypted' => null,
                'PaymentFacilitatorData' => null,
                'AdditionalInfoData' => null,
                'CardInformationData' => [
                    'CardNo' => '5555444433332222',
                    'ExpireDate' => '2112',
                    'Cvc2' => '122',
                    'CardHolderName' => 'ahmet',
                ],
                'IsMailOrder' => 'N',
                'IsRecurring' => null,
                'IsTDSecureMerchant' => null,
                'PaymentInstrumentType' => 'CARD',
                'ThreeDSecureData' => null,
                'Amount' => 175,
                'CurrencyCode' => 'TL',
                'OrderId' => '0000000620093100_024',
                'InstallmentCount' => '0',
                'InstallmentType' => 'N',
                'KOICode' => null,
                'MerchantMessageData' => null,
                'PointAmount' => null,
                'MAC' => 'ACUIQYdc6CDEoGqii4E/9Ec8cnN4++LmtrJvR8cn17A=',
            ],
            'expected' => '{"ApiType":"JSON","ApiVersion":"V100","MACParams":"MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount","MerchantNo":"6700950031","TerminalNo":"67540050","CipheredData":null,"DealerData":null,"IsEncrypted":null,"PaymentFacilitatorData":null,"AdditionalInfoData":null,"CardInformationData":{"CardNo":"5555444433332222","ExpireDate":"2112","Cvc2":"122","CardHolderName":"ahmet"},"IsMailOrder":"N","IsRecurring":null,"IsTDSecureMerchant":null,"PaymentInstrumentType":"CARD","ThreeDSecureData":null,"Amount":175,"CurrencyCode":"TL","OrderId":"0000000620093100_024","InstallmentCount":"0","InstallmentType":"N","KOICode":null,"MerchantMessageData":null,"PointAmount":null,"MAC":"ACUIQYdc6CDEoGqii4E\/9Ec8cnN4++LmtrJvR8cn17A="}',
        ];
    }

    public static function decodeDataProvider(): iterable
    {
        yield [
            'input'    => '{"abc": true}',
            'expected' => [
                'abc' => true,
            ],
        ];
    }
}
