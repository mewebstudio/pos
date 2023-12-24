<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\PayForPosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\PayForPosSerializer
 */
class PayForPosSerializerTest extends TestCase
{
    private PayForPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PayForPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PayForPos::class);

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
    public function testDecode(string $data, string $txType, array $expected): void
    {
        $result = $this->serializer->decode($data, $txType);

        $this->assertSame($expected, $result);
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => '<?xml version="1.0" encoding="utf-8"?>
<PayforResponse>
<AuthCode>S31432</AuthCode>
<HostRefNum>326011208369</HostRefNum>
<ProcReturnCode>00</ProcReturnCode>
<TransId>20230917EF0E</TransId>
<ErrMsg>Onaylandı</ErrMsg>
<CardHolderName>John Doe</CardHolderName>
<ArtiTaksit>0</ArtiTaksit>
<BankInternalResponseMessage></BankInternalResponseMessage>
<PAYFORFROMXMLREQUEST>1</PAYFORFROMXMLREQUEST>
<SESSION_SYSTEM_USER>0</SESSION_SYSTEM_USER>
</PayforResponse>',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'AuthCode'                    => 'S31432',
                'HostRefNum'                  => '326011208369',
                'ProcReturnCode'              => '00',
                'TransId'                     => '20230917EF0E',
                'ErrMsg'                      => 'Onaylandı',
                'CardHolderName'              => 'John Doe',
                'ArtiTaksit'                  => '0',
                'BankInternalResponseMessage' => '',
                'PAYFORFROMXMLREQUEST'        => '1',
                'SESSION_SYSTEM_USER'         => '0',
            ],
        ];

        yield 'test_history' => [
            'input'    => '{
  "PaymentRequest": {
    "RequestGuid": 0,
    "InsertDatetime": "0001-01-01T00:00:00",
    "MbrId": 5,
    "MerchantID": "085300000009704",
    "RequestIp": "88.152.9.140",
    "RequestStat": "1,10",
    "SecureType": "Report",
    "Exponent": "2",
    "Currency": 949,
    "TerminalID": "VS010481",
    "TxnType": "TxnHistory",
    "CardType": "",
    "Lang": "TR",
    "BonusAmount": "",
    "InstallmentCount": 0,
    "AlphaCode": "TL",
    "Ecommerce": 1,
    "MrcCountryCode": "792",
    "MrcName": "3D PAY TEST ISYERI",
    "MerchantHomeUrl": "https://vpostest.qnbfinansbank.com/",
    "TxnStatus": "P",
    "BatchNo": 9296,
    "ReqId": 83893796,
    "UsedPoint": 0.0,
    "SrcType": "VPO",
    "RefundedAmount": 0.0,
    "RefundedPoint": 0.0,
    "ReqDate": 0,
    "SysDate": 0,
    "F11": 209159,
    "VposElapsedTime": 0,
    "BankingElapsedTime": 0,
    "SocketElapsedTime": 0,
    "HsmElapsedTime": 0,
    "MpiElapsedTime": 0,
    "hasOrderId": true,
    "TemplateType": 0,
    "HasAddressCount": false,
    "IsPaymentFacilitator": false,
    "F11_ORG": 0,
    "F12_ORG": 0,
    "F22_ORG": 0,
    "F25_ORG": 0,
    "MTI_ORG": 0,
    "IntervalType": 0,
    "IntervalDuration": 0,
    "RepeatCount": 0,
    "RequestClientIp": "88.152.9.140",
    "VoidTime": 0,
    "PaymentLinkId": 0,
    "ArtiTaksit": 0
  },
  "PaymentAddress": {
    "RequestGuid": 0
  },
  "ExtraParameters": [
    [
      "INCOMING_DATA",
      "085300000009704QNB_API_KULLANICI_3DPAY*****5ReportTxnHistorytr"
    ],
    [
      "PAYFORFROMXMLREQUEST",
      "1"
    ],
    [
      "SESSION_MRC_CODE",
      "085300000009704"
    ],
    [
      "SESSION_SYSTEM_USER",
      "0"
    ]
  ]
}',
            'txType'   => PosInterface::TX_TYPE_HISTORY,
            'expected' => [
                'PaymentRequest'  => [
                    'RequestGuid'          => 0,
                    'InsertDatetime'       => '0001-01-01T00:00:00',
                    'MbrId'                => 5,
                    'MerchantID'           => '085300000009704',
                    'RequestIp'            => '88.152.9.140',
                    'RequestStat'          => '1,10',
                    'SecureType'           => 'Report',
                    'Exponent'             => '2',
                    'Currency'             => 949,
                    'TerminalID'           => 'VS010481',
                    'TxnType'              => 'TxnHistory',
                    'CardType'             => '',
                    'Lang'                 => 'TR',
                    'BonusAmount'          => '',
                    'InstallmentCount'     => 0,
                    'AlphaCode'            => 'TL',
                    'Ecommerce'            => 1,
                    'MrcCountryCode'       => '792',
                    'MrcName'              => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'      => 'https://vpostest.qnbfinansbank.com/',
                    'TxnStatus'            => 'P',
                    'BatchNo'              => 9296,
                    'ReqId'                => 83893796,
                    'UsedPoint'            => 0.0,
                    'SrcType'              => 'VPO',
                    'RefundedAmount'       => 0.0,
                    'RefundedPoint'        => 0.0,
                    'ReqDate'              => 0,
                    'SysDate'              => 0,
                    'F11'                  => 209159,
                    'VposElapsedTime'      => 0,
                    'BankingElapsedTime'   => 0,
                    'SocketElapsedTime'    => 0,
                    'HsmElapsedTime'       => 0,
                    'MpiElapsedTime'       => 0,
                    'hasOrderId'           => true,
                    'TemplateType'         => 0,
                    'HasAddressCount'      => false,
                    'IsPaymentFacilitator' => false,
                    'F11_ORG'              => 0,
                    'F12_ORG'              => 0,
                    'F22_ORG'              => 0,
                    'F25_ORG'              => 0,
                    'MTI_ORG'              => 0,
                    'IntervalType'         => 0,
                    'IntervalDuration'     => 0,
                    'RepeatCount'          => 0,
                    'RequestClientIp'      => '88.152.9.140',
                    'VoidTime'             => 0,
                    'PaymentLinkId'        => 0,
                    'ArtiTaksit'           => 0,
                ],
                'PaymentAddress'  => [
                    'RequestGuid' => 0,
                ],
                'ExtraParameters' => [
                    0 => [
                        0 => 'INCOMING_DATA',
                        1 => '085300000009704QNB_API_KULLANICI_3DPAY*****5ReportTxnHistorytr',
                    ],
                    1 => [
                        0 => 'PAYFORFROMXMLREQUEST',
                        1 => '1',
                    ],
                    2 => [
                        0 => 'SESSION_MRC_CODE',
                        1 => '085300000009704',
                    ],
                    3 => [
                        0 => 'SESSION_SYSTEM_USER',
                        1 => '0',
                    ],
                ],
            ],
        ];
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => [
                'MerchantId'       => '085300000009704',
                'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                'UserPass'         => 'UcBN0',
                'MbrId'            => '5',
                'MOTO'             => '0',
                'OrderId'          => 'order222',
                'SecureType'       => 'NonSecure',
                'TxnType'          => 'Auth',
                'PurchAmount'      => '100.25',
                'Currency'         => '949',
                'InstallmentCount' => '0',
                'Lang'             => 'tr',
                'CardHolderName'   => 'ahmet',
                'Pan'              => '5555444433332222',
                'Expiry'           => '1221',
                'Cvv2'             => '122',
            ],
            'expected' => '<?xml version="1.0" encoding="UTF-8"?>
<PayforRequest><MerchantId>085300000009704</MerchantId><UserCode>QNB_API_KULLANICI_3DPAY</UserCode><UserPass>UcBN0</UserPass><MbrId>5</MbrId><MOTO>0</MOTO><OrderId>order222</OrderId><SecureType>NonSecure</SecureType><TxnType>Auth</TxnType><PurchAmount>100.25</PurchAmount><Currency>949</Currency><InstallmentCount>0</InstallmentCount><Lang>tr</Lang><CardHolderName>ahmet</CardHolderName><Pan>5555444433332222</Pan><Expiry>1221</Expiry><Cvv2>122</Cvv2></PayforRequest>
',
        ];
    }
}
