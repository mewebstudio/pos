<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\KuveytSoapApiPosSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\KuveytSoapApiPosSerializer
 */
class KuveytSoapApiPosSerializerTest extends TestCase
{
    private KuveytSoapApiPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new KuveytSoapApiPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(KuveytSoapApiPos::class);

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

    public static function encodeDataProvider(): Generator
    {
        $refundTests = iterator_to_array(KuveytSoapApiPosRequestDataMapperTest::createRefundRequestDataProvider());

        yield 'test_refund' => [
            'input'           => $refundTests[0]['expected'],
            'txType'          => PosInterface::TX_TYPE_REFUND,
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:DrawBack><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>101</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>DrawBack</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>101</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>101</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:DrawBack></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_partial_refund' => [
            'input'           => $refundTests[1]['expected'],
            'txType'          => PosInterface::TX_TYPE_REFUND_PARTIAL,
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:PartialDrawback><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>901</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>PartialDrawback</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>901</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>901</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:PartialDrawback></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_cancel' => [
            'input'           => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'txType'          => PosInterface::TX_TYPE_CANCEL,
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_status' => [
            'input'           => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'txType'          => PosInterface::TX_TYPE_STATUS,
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];
    }

    public static function decodeXmlDataProvider(): iterable
    {
        yield 'test_cancel' => [
            'input'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><SaleReversalResponse xmlns="http://boa.net/BOA.Integration.VirtualPos/Service"><SaleReversalResult><Results><Result><ErrorMessage>İptal işlemi satışla aynı gün yapılmalıdır. Geçmiş tarihli işlem için iade yapınız.</ErrorMessage><ErrorCode>InvalidRequestError</ErrorCode><IsFriendly>true</IsFriendly><Severity>BusinessError</Severity></Result></Results><Success>false</Success><Value><IsEnrolled>false</IsEnrolled><IsVirtual>false</IsVirtual><ResponseCode>DbLayerError</ResponseCode><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><MerchantId xsi:nil="true"/><BusinessKey>0</BusinessKey></Value></SaleReversalResult></SaleReversalResponse></s:Body></s:Envelope>',
            'txType'   => PosInterface::TX_TYPE_CANCEL,
            'expected' => [
                'SaleReversalResponse' => [
                    'SaleReversalResult' => [
                        'Results' => [
                            'Result' => [
                                'ErrorMessage' => 'İptal işlemi satışla aynı gün yapılmalıdır. Geçmiş tarihli işlem için iade yapınız.',
                                'ErrorCode'    => 'InvalidRequestError',
                                'IsFriendly'   => 'true',
                                'Severity'     => 'BusinessError',
                            ],
                        ],
                        'Success' => 'false',
                        'Value'   => [
                            'IsEnrolled'      => 'false',
                            'IsVirtual'       => 'false',
                            'ResponseCode'    => 'DbLayerError',
                            'OrderId'         => '0',
                            'TransactionTime' => '0001-01-01T00:00:00',
                            'MerchantId'      => [
                                '@xsi:nil' => 'true',
                                '#'        => '',
                            ],
                            'BusinessKey'     => '0',
                        ],
                    ],
                ],
            ],
        ];
        yield 'test_refund' => [
            'input'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><PartialDrawbackResponse xmlns="http://boa.net/BOA.Integration.VirtualPos/Service"><PartialDrawbackResult><Results><Result><ErrorMessage>IsoProxyFactoryServiceResponseWasNull</ErrorMessage><ErrorCode>ServiceUnavailable</ErrorCode><IsFriendly>true</IsFriendly><Severity>BusinessError</Severity></Result></Results><Success>false</Success><Value><IsEnrolled>false</IsEnrolled><IsVirtual>false</IsVirtual><ResponseCode>DbLayerError</ResponseCode><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><MerchantId xsi:nil="true"/><BusinessKey>0</BusinessKey></Value></PartialDrawbackResult></PartialDrawbackResponse></s:Body></s:Envelope>',
            'txType'   => PosInterface::TX_TYPE_REFUND,
            'expected' => [
                "PartialDrawbackResponse" => [
                    "PartialDrawbackResult" => [
                        "Results" => [
                            "Result" => [
                                "ErrorMessage" => "IsoProxyFactoryServiceResponseWasNull",
                                "ErrorCode"    => "ServiceUnavailable",
                                "IsFriendly"   => "true",
                                "Severity"     => "BusinessError",
                            ],
                        ],
                        "Success" => "false",
                        "Value"   => [
                            "IsEnrolled"      => "false",
                            "IsVirtual"       => "false",
                            "ResponseCode"    => "DbLayerError",
                            "OrderId"         => "0",
                            "TransactionTime" => "0001-01-01T00:00:00",
                            "MerchantId"      => [
                                "@xsi:nil" => "true",
                                "#"        => "",
                            ],
                            "BusinessKey"     => "0",
                        ],
                    ],
                ],
            ],
        ];
        yield 'test_status' => [
            'input'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><GetMerchantOrderDetailResponse xmlns="http://boa.net/BOA.Integration.VirtualPos/Service"><GetMerchantOrderDetailResult><Results/><Success>true</Success><Value><OrderContract><IsSelected>false</IsSelected><IsSelectable>true</IsSelectable><OrderId>302704156</OrderId><MerchantOrderId>20260209089B</MerchantOrderId><MerchantId>496</MerchantId><CardHolderName>John Doe</CardHolderName><CardType>MasterCard</CardType><CardNumber>518896******2544</CardNumber><OrderDate>2026-02-09T19:10:09.097</OrderDate><OrderStatus>1</OrderStatus><LastOrderStatus>6</LastOrderStatus><OrderType>1</OrderType><TransactionStatus>1</TransactionStatus><FirstAmount>10.01</FirstAmount><CancelAmount>10.01</CancelAmount><DrawbackAmount>0.00</DrawbackAmount><ClosedAmount>0.00</ClosedAmount><FEC>0949</FEC><VPSEntryMode>ECOM</VPSEntryMode><InstallmentCount>0</InstallmentCount><TransactionSecurity>3</TransactionSecurity><ResponseCode>00</ResponseCode><ResponseExplain>İşlem gerçekleştirildi.</ResponseExplain><EndOfDayStatus>2</EndOfDayStatus><TransactionSide>Auto</TransactionSide><CardHolderIPAddress/><MerchantIPAddress>207.211.215.148</MerchantIPAddress><MerchantUserName>apitest</MerchantUserName><ProvNumber>004212</ProvNumber><BatchId>623</BatchId><CardExpireDate>2906</CardExpireDate><PosTerminalId>VP008759</PosTerminalId><Explain/><Explain2/><Explain3/><RRN>604019659177</RRN><Stan>659177</Stan><UserName>vposuser2</UserName><HostName>STD8BOATEST1</HostName><SystemDate>2026-02-09T19:10:09.087</SystemDate><UpdateUserName>webgate2</UpdateUserName><UpdateHostName>STD8BOATEST1</UpdateHostName><UpdateSystemDate>2026-02-09T19:10:20.703</UpdateSystemDate><EndOfDayDate xsi:nil="true"/><HostIP>172.20.8.84</HostIP><FECAmount>0</FECAmount><IdentityTaxNumber/><QueryId>0</QueryId><DebtId>0</DebtId><DebtorName/><Period/><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><DeferringCount xsi:nil="true"/></OrderContract></Value></GetMerchantOrderDetailResult></GetMerchantOrderDetailResponse></s:Body></s:Envelope>',
            'txType'   => PosInterface::TX_TYPE_STATUS,
            'expected' => [
                'GetMerchantOrderDetailResponse' => [
                    'GetMerchantOrderDetailResult' => [
                        'Results' => '',
                        'Success' => 'true',
                        'Value'   => [
                            'OrderContract' => [
                                'IsSelected'          => 'false',
                                'IsSelectable'        => 'true',
                                'OrderId'             => '302704156',
                                'MerchantOrderId'     => '20260209089B',
                                'MerchantId'          => '496',
                                'CardHolderName'      => 'John Doe',
                                'CardType'            => 'MasterCard',
                                'CardNumber'          => '518896******2544',
                                'OrderDate'           => '2026-02-09T19:10:09.097',
                                'OrderStatus'         => '1',
                                'LastOrderStatus'     => '6',
                                'OrderType'           => '1',
                                'TransactionStatus'   => '1',
                                'FirstAmount'         => '10.01',
                                'CancelAmount'        => '10.01',
                                'DrawbackAmount'      => '0.00',
                                'ClosedAmount'        => '0.00',
                                'FEC'                 => '0949',
                                'VPSEntryMode'        => 'ECOM',
                                'InstallmentCount'    => '0',
                                'TransactionSecurity' => '3',
                                'ResponseCode'        => '00',
                                'ResponseExplain'     => 'İşlem gerçekleştirildi.',
                                'EndOfDayStatus'      => '2',
                                'TransactionSide'     => 'Auto',
                                'CardHolderIPAddress' => '',
                                'MerchantIPAddress'   => '207.211.215.148',
                                'MerchantUserName'    => 'apitest',
                                'ProvNumber'          => '004212',
                                'BatchId'             => '623',
                                'CardExpireDate'      => '2906',
                                'PosTerminalId'       => 'VP008759',
                                'Explain'             => '',
                                'Explain2'            => '',
                                'Explain3'            => '',
                                'RRN'                 => '604019659177',
                                'Stan'                => '659177',
                                'UserName'            => 'vposuser2',
                                'HostName'            => 'STD8BOATEST1',
                                'SystemDate'          => '2026-02-09T19:10:09.087',
                                'UpdateUserName'      => 'webgate2',
                                'UpdateHostName'      => 'STD8BOATEST1',
                                'UpdateSystemDate'    => '2026-02-09T19:10:20.703',
                                'EndOfDayDate'        => [
                                    '@xsi:nil' => 'true',
                                    '#'        => '',
                                ],

                                'HostIP'            => '172.20.8.84',
                                'FECAmount'         => '0',
                                'IdentityTaxNumber' => '',
                                'QueryId'           => '0',
                                'DebtId'            => '0',
                                'DebtorName'        => '',
                                'Period'            => '',
                                'SurchargeAmount'   => '0',
                                'SGKDebtAmount'     => '0',
                                'DeferringCount'    => [
                                    '@xsi:nil' => 'true',
                                    '#'        => '',
                                ],

                            ],

                        ],

                    ],

                ],

            ],
        ];
    }
}
