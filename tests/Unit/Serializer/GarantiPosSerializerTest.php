<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\GarantiPosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\GarantiPosSerializer
 */
class GarantiPosSerializerTest extends TestCase
{
    private GarantiPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new GarantiPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(GarantiPos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expected): void
    {
        $result   = $this->serializer->encode($data);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, string $txType, array $expected): void
    {
        $actual = $this->serializer->decode($input, $txType);

        $this->assertSame($expected, $actual);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => [
                'Mode'        => 'TEST',
                'Version'     => 'v0.01',
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => '3732634F78053D42304B0966E263629FE44E258B',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress'    => '',
                    'EmailAddress' => 'test@test.com',
                ],
                'Card'        => [
                    'Number'     => '5555444433332222',
                    'ExpireDate' => '1221',
                    'CVV2'       => '122',
                ],
                'Order'       => [
                    'OrderID'     => 'order222',
                ],
                'Transaction' => [
                    'Type'                  => 'sales',
                    'InstallmentCnt'        => '',
                    'Amount'                => 10025,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
            ],
            'expected' => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version><Terminal><ProvUserID>PROVAUT</ProvUserID><UserID>PROVAUT</UserID><HashData>3732634F78053D42304B0966E263629FE44E258B</HashData><ID>30691298</ID><MerchantID>7000679</MerchantID></Terminal><Customer><IPAddress></IPAddress><EmailAddress>test@test.com</EmailAddress></Customer><Card><Number>5555444433332222</Number><ExpireDate>1221</ExpireDate><CVV2>122</CVV2></Card><Order><OrderID>order222</OrderID></Order><Transaction><Type>sales</Type><InstallmentCnt></InstallmentCnt><Amount>10025</Amount><CurrencyCode>949</CurrencyCode><CardholderPresentCode>0</CardholderPresentCode><MotoInd>N</MotoInd></Transaction></GVPSRequest>
',
        ];
    }

    public static function decodeDataProvider(): iterable
    {
        yield 'test1' => [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version><Terminal><ProvUserID>PROVAUT</ProvUserID><UserID>PROVAUT</UserID><HashData>8DD74209DEEB7D333105E1C69998A827419A3B04</HashData><ID>30691298</ID><MerchantID>7000679</MerchantID></Terminal><Customer><IPAddress>127.15.15.1</IPAddress><EmailAddress>email@example.com</EmailAddress></Customer><Order><OrderID>2020110828BC</OrderID></Order><Transaction><Type>orderinq</Type><InstallmentCnt></InstallmentCnt><Amount>100</Amount><CurrencyCode>949</CurrencyCode><CardholderPresentCode>0</CardholderPresentCode><MotoInd>N</MotoInd></Transaction></GVPSRequest>
',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'Mode'        => 'TEST',
                'Version'     => 'v0.01',
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress'    => '127.15.15.1',
                    'EmailAddress' => 'email@example.com',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'orderinq',
                    'InstallmentCnt'        => '',
                    'Amount'                => '100',
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
            ],
        ];
    }
}
