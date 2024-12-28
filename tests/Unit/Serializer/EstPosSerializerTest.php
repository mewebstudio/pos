<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EstPosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\EstPosSerializer
 */
class EstPosSerializerTest extends TestCase
{
    private EstPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new EstPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(EstPos::class);

        $this->assertTrue($supports);

        $supports = $this->serializer::supports(EstV3Pos::class);

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
            'input' => [
                'Name' => 'ISBANKAPI',
                'Password' => 'ISBANK07',
                'ClientId' => '700655000200',
                'Type' => 'Auth',
                'IPAddress' => '',
                'Email' => 'test@test.com',
                'OrderId' => 'order222',
                'UserId' => '',
                'Total' => '100.25',
                'Currency' => '949',
                'Taksit' => '',
                'Number' => '5555444433332222',
                'Expires' => '12/21',
                'Cvv2Val' => '122',
                'Mode' => 'P',
            ],
            'expected' => '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Request><Name>ISBANKAPI</Name><Password>ISBANK07</Password><ClientId>700655000200</ClientId><Type>Auth</Type><IPAddress></IPAddress><Email>test@test.com</Email><OrderId>order222</OrderId><UserId></UserId><Total>100.25</Total><Currency>949</Currency><Taksit></Taksit><Number>5555444433332222</Number><Expires>12/21</Expires><Cvv2Val>122</Cvv2Val><Mode>P</Mode></CC5Request>
',
        ];
    }

    public static function decodeDataProvider(): Generator
    {
        yield '3d_payment_success_response' => [
            'input'    => '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Response>
  <OrderId>20230910AF6A</OrderId>
  <GroupId>20230910AF6A</GroupId>
  <Response>Approved</Response>
  <AuthCode>P18552</AuthCode>
  <HostRefNum>325300733333</HostRefNum>
  <ProcReturnCode>00</ProcReturnCode>
  <TransId>23253WkfD10806</TransId>
  <ErrMsg></ErrMsg>
  <Extra>
    <SETTLEID>2589</SETTLEID>
    <TRXDATE>20230910 22:36:30</TRXDATE>
    <ERRORCODE></ERRORCODE>
    <TERMINALID>00655020</TERMINALID>
    <MERCHANTID>655000200</MERCHANTID>
    <CARDBRAND>VISA</CARDBRAND>
    <CARDISSUER>Z&#x130;RAAT BANKASI</CARDISSUER>
    <AVSAPPROVE>Y</AVSAPPROVE>
    <HOSTDATE>0910-223632</HOSTDATE>
    <AVSERRORCODEDETAIL>avshatali-avshatali-avshatali-avshatali-</AVSERRORCODEDETAIL>
    <NUMCODE>00</NUMCODE>
  </Extra>
</CC5Response>',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'OrderId'        => '20230910AF6A',
                'GroupId'        => '20230910AF6A',
                'Response'       => 'Approved',
                'AuthCode'       => 'P18552',
                'HostRefNum'     => '325300733333',
                'ProcReturnCode' => '00',
                'TransId'        => '23253WkfD10806',
                'ErrMsg'         => '',
                'Extra'          => [
                    'SETTLEID'           => '2589',
                    'TRXDATE'            => '20230910 22:36:30',
                    'ERRORCODE'          => '',
                    'TERMINALID'         => '00655020',
                    'MERCHANTID'         => '655000200',
                    'CARDBRAND'          => 'VISA',
                    'CARDISSUER'         => 'ZİRAAT BANKASI',
                    'AVSAPPROVE'         => 'Y',
                    'HOSTDATE'           => '0910-223632',
                    'AVSERRORCODEDETAIL' => 'avshatali-avshatali-avshatali-avshatali-',
                    'NUMCODE'            => '00',
                ],
            ],
        ];
    }
}
