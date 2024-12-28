<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\VakifKatilimPosSerializer;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\VakifKatilimPosSerializer
 */
class VakifKatilimPosSerializerTest extends TestCase
{
    private VakifKatilimPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new VakifKatilimPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(VakifKatilimPos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $input, string $expected): void
    {
        $actual   = $this->serializer->encode($input, PosInterface::TX_TYPE_PAY_AUTH);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider decodeHtmlDataProvider
     */
    public function testDecodeHtml(string $input, array $expected): void
    {
        $actual = $this->serializer->decode($input, PosInterface::TX_TYPE_PAY_AUTH);

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

    /**
     * @dataProvider decodeXmlDataProvider
     */
    public function testDecodeXML(string $input, string $txType, array $expected): void
    {
        $actual = $this->serializer->decode($input, $txType);

        $this->assertSame($expected, $actual);
    }

    public static function decodeHtmlDataProvider(): array
    {
        $vakifKatilimHTML = <<<HTML
<!DOCTYPE html>
<html xmlns="http//www.w3.org/1999/xhtml">
<head>
<title></title>
</head>
<body onload="OnLoadEvent();">
<form name="downloadForm" action="https://localhost/VirtualPos/ThreeDModel/Fail"
method="POST">
<input type="hidden" name="ResponseCode" value="CardNotEnrolled">
<input type="hidden" name="ResponseMessage" value="Card 3D Secure kayitli degil.">
<input type="hidden" name="ProvisionNumber">
<input type="hidden" name="MerchantOrderId">
<input type="hidden" name="OrderId" value="0">
<input type="hidden" name="RRN">
<input type="hidden" name="Stan">
<input type="hidden" name="HashData">
<input type="hidden" name="MD">
<!-- To support javascript unaware/disabled browsers -->
<noscript>
<center>
Please click the submit button below.<br>
<input type="submit" name="submit" value="Submit">
 </center>
</noscript>
</form>
<script language="Javascript">
<!--
function OnLoadEvent() {
 document.downloadForm.submit();
 }
 //
-->
</script>
</body>
</html>
HTML;

        return [
            '3d_auth_fail' => [
                'html'     => $vakifKatilimHTML,
                'expected' => [
                    'gateway'     => 'https://localhost/VirtualPos/ThreeDModel/Fail',
                    'form_inputs' => [
                        'ResponseCode'    => 'CardNotEnrolled',
                        'ResponseMessage' => 'Card 3D Secure kayitli degil.',
                        'ProvisionNumber' => '',
                        'MerchantOrderId' => '',
                        'OrderId'         => '0',
                        'RRN'             => '',
                        'Stan'            => '',
                        'HashData'        => '',
                        'MD'              => '',
                    ],
                ],
            ],
        ];
    }

    public static function decodeXmlDataProvider(): iterable
    {
        yield [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?>
<VPosTransactionResponseContract xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <VPosMessageContract>
<OkUrl>http://localhost/ThreeDModel/Approval</OkUrl>
<FailUrl>http://localhost/ThreeDModel/Fail</FailUrl>
<HashData>DvAUXMvYV4ex5m16mMezEl+kxrI=</HashData>
<MerchantId>1</MerchantId>
<SubMerchantId>0</SubMerchantId>
<CustomerId>936</CustomerId>
<UserName>APIUSER</UserName>
<HashPassword>kfkdsnskslkclswr9430ır</HashPassword>
<MerchantOrderId>1554891870</MerchantOrderId>
<InstallmentCount>0</InstallmentCount>
<Amount>111</Amount>
<FECAmount>0</FECAmount>
<AdditionalData>
 <AdditionalDataList>
 	<VPosAdditionalData>
 <Key>MD</Key>
 <Data>vygnTBD4smBxAOlDsgbaOQ==</Data>
 </VPosAdditionalData>
 </AdditionalDataList>
</AdditionalData>
<Products/>
<Addresses/>
<PaymentType>1</PaymentType>
<DebtId>0</DebtId>
<SurchargeAmount>0</SurchargeAmount>
<SGKDebtAmount>0</SGKDebtAmount>
<InstallmentMaturityCommisionFlag>0</InstallmentMaturityCommisionFlag>
<TransactionSecurity>3</TransactionSecurity>
 </VPosMessageContract>
 <IsEnrolled>true</IsEnrolled>
 <IsVirtual>false</IsVirtual>
 <RRN>922709016599</RRN>
 <Stan>016599</Stan>
 <ResponseCode>00</ResponseCode>
 <ResponseMessage>Provizyon Alindi.</ResponseMessage>
 <OrderId>15161</OrderId>
 <TransactionTime>00010101T00:00:00</TransactionTime>
 <MerchantOrderId>1554891870</MerchantOrderId>
 <HashData>bcCqBe4hbElPOVYtfvsw7M44usQ=</HashData>
</VPosTransactionResponseContract>',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'VPosMessageContract' => [
                    'OkUrl'                            => 'http://localhost/ThreeDModel/Approval',
                    'FailUrl'                          => 'http://localhost/ThreeDModel/Fail',
                    'HashData'                         => 'DvAUXMvYV4ex5m16mMezEl+kxrI=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '936',
                    'UserName'                         => 'APIUSER',
                    'HashPassword'                     => 'kfkdsnskslkclswr9430ır',
                    'MerchantOrderId'                  => '1554891870',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '111',
                    'FECAmount'                        => '0',
                    'AdditionalData'                   => [
                        'AdditionalDataList' => [
                            'VPosAdditionalData' => [
                                'Key'  => 'MD',
                                'Data' => 'vygnTBD4smBxAOlDsgbaOQ==',
                            ],
                        ],
                    ],
                    'Products'                         => '',
                    'Addresses'                        => '',
                    'PaymentType'                      => '1',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'TransactionSecurity'              => '3',
                ],
                'IsEnrolled'          => 'true',
                'IsVirtual'           => 'false',
                'RRN'                 => '922709016599',
                'Stan'                => '016599',
                'ResponseCode'        => '00',
                'ResponseMessage'     => 'Provizyon Alindi.',
                'OrderId'             => '15161',
                'TransactionTime'     => '00010101T00:00:00',
                'MerchantOrderId'     => '1554891870',
                'HashData'            => 'bcCqBe4hbElPOVYtfvsw7M44usQ=',
                '@xmlns:xsi'          => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'          => 'http://www.w3.org/2001/XMLSchema',
            ],
        ];

        $testUtf16 = <<<A_WRAP
<?xml version="1.0" encoding="utf-16"?>
<VPosTransactionResponseContract xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <VPosOrderData>
 <OrderContract>
 <OrderId>12743</OrderId>
 <MerchantOrderId>1995434716</MerchantOrderId>
 <MerchantId>1</MerchantId>
 <PosTerminalId>111111</PosTerminalId>
 <OrderStatus>1</OrderStatus>
 <OrderStatusDescription />
 <OrderType>1</OrderType>
 <OrderTypeDescription />
 <TransactionStatus>1</TransactionStatus>
 <TransactionStatusDescription>Basarili</TransactionStatusDescription>
 <LastOrderStatus>1</LastOrderStatus>
 <LastOrderStatusDescription />
 <EndOfDayStatus>1</EndOfDayStatus>
 <EndOfDayStatusDescription>Acik</EndOfDayStatusDescription>
 <FEC>0949</FEC>
 <FecDescription>TRY</FecDescription>
 <TransactionSecurity>1</TransactionSecurity>
 <TransactionSecurityDescription>3d'siz islem</TransactionSecurityDescription>
 <CardHolderName>Hasan Karacan</CardHolderName>
 <CardType>MasterCard</CardType>
 <CardNumber>5353********7017</CardNumber>
 <OrderDate>2020-12-24T09:21:41.55</OrderDate>
 <FirstAmount>9.30</FirstAmount>
 <FECAmount>0.00</FECAmount>
 <CancelAmount>0.00</CancelAmount>
 <DrawbackAmount>0.00</DrawbackAmount>
 <ClosedAmount>0.00</ClosedAmount>
 <InstallmentCount>0</InstallmentCount>
 <ResponseCode>00</ResponseCode>
 <ResponseExplain>Provizyon alındı.</ResponseExplain>
 <ProvNumber>043290</ProvNumber>
 <RRN>035909014127</RRN>
 <Stan>014127</Stan>
 <MerchantUserName>USERNAME</MerchantUserName>
 <BatchId>69</BatchId>
 </OrderContract>
 </VPosOrderData>
 <ResponseCode>00</ResponseCode>
 <ResponseMessage />
</VPosTransactionResponseContract>
A_WRAP
        ;
        yield 'test_utf_16' => [
            'input'    => $testUtf16,
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'VPosOrderData' => [
                    'OrderContract' => [
                        'OrderId'                        => '12743',
                        'MerchantOrderId'                => '1995434716',
                        'MerchantId'                     => '1',
                        'PosTerminalId'                  => '111111',
                        'OrderStatus'                    => '1',
                        'OrderStatusDescription'         => '',
                        'OrderType'                      => '1',
                        'OrderTypeDescription'           => '',
                        'TransactionStatus'              => '1',
                        'TransactionStatusDescription'   => 'Basarili',
                        'LastOrderStatus'                => '1',
                        'LastOrderStatusDescription'     => '',
                        'EndOfDayStatus'                 => '1',
                        'EndOfDayStatusDescription'      => 'Acik',
                        'FEC'                            => '0949',
                        'FecDescription'                 => 'TRY',
                        'TransactionSecurity'            => '1',
                        'TransactionSecurityDescription' => "3d'siz islem",
                        'CardHolderName'                 => 'Hasan Karacan',
                        'CardType'                       => 'MasterCard',
                        'CardNumber'                     => '5353********7017',
                        'OrderDate'                      => '2020-12-24T09:21:41.55',
                        'FirstAmount'                    => '9.30',
                        'FECAmount'                      => '0.00',
                        'CancelAmount'                   => '0.00',
                        'DrawbackAmount'                 => '0.00',
                        'ClosedAmount'                   => '0.00',
                        'InstallmentCount'               => '0',
                        'ResponseCode'                   => '00',
                        'ResponseExplain'                => 'Provizyon alındı.',
                        'ProvNumber'                     => '043290',
                        'RRN'                            => '035909014127',
                        'Stan'                           => '014127',
                        'MerchantUserName'               => 'USERNAME',
                        'BatchId'                        => '69',
                    ],

                ],

                'ResponseCode'    => '00',
                'ResponseMessage' => '',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
        ];
    }

    public static function decodeExceptionDataProvider(): Generator
    {
        yield 'test1' => [
            'input'                    => <<<A_WRAP
<!DOCTYPE html>
<html>
    <head>
        <title>Runtime Error</title>
        <meta name="viewport" content="width=device-width" />
        <style>
         body {font-family:"Verdana";font-weight:normal;font-size: .7em;color:black;}
         p {font-family:"Verdana";font-weight:normal;color:black;margin-top: -5px}
         b {font-family:"Verdana";font-weight:bold;color:black;margin-top: -5px}
         H1 { font-family:"Verdana";font-weight:normal;font-size:18pt;color:red }
         H2 { font-family:"Verdana";font-weight:normal;font-size:14pt;color:maroon }
         pre {font-family:"Consolas","Lucida Console",Monospace;font-size:11pt;margin:0;padding:0.5em;line-height:14pt}
         .marker {font-weight: bold; color: black;text-decoration: none;}
         .version {color: gray;}
         .error {margin-bottom: 10px;}
         .expandable { text-decoration:underline; font-weight:bold; color:navy; cursor:hand; }
         @media screen and (max-width: 639px) {
          pre { width: 440px; overflow: auto; white-space: pre-wrap; word-wrap: break-word; }
         }
         @media screen and (max-width: 479px) {
          pre { width: 280px; }
         }
        </style>
    </head>

    <body bgcolor="white">

            <span><H1>Server Error in '/VirtualPOS.Gateway' Application.<hr width=100% size=1 color=silver></H1>

            <h2> <i>Runtime Error</i> </h2></span>

            <font face="Arial, Helvetica, Geneva, SunSans-Regular, sans-serif ">

            <b> Description: </b>An application error occurred on the server. The current custom error settings for this application prevent the details of the application error from being viewed remotely (for security reasons). It could, however, be viewed by browsers running on the local server machine.
            <br><br>

            <b>Details:</b> To enable the details of this specific error message to be viewable on remote machines, please create a &lt;customErrors&gt; tag within a &quot;web.config&quot; configuration file located in the root directory of the current web application. This &lt;customErrors&gt; tag should then have its &quot;mode&quot; attribute set to &quot;Off&quot;.<br><br>

            <table width=100% bgcolor="#ffffcc">
               <tr>
                  <td>
                      <code><pre>

&lt;!-- Web.Config Configuration File --&gt;

&lt;configuration&gt;
    &lt;system.web&gt;
        &lt;customErrors mode=&quot;Off&quot;/&gt;
    &lt;/system.web&gt;
&lt;/configuration&gt;</pre></code>

                  </td>
               </tr>
            </table>

            <br>

            <b>Notes:</b> The current error page you are seeing can be replaced by a custom error page by modifying the &quot;defaultRedirect&quot; attribute of the application&#39;s &lt;customErrors&gt; configuration tag to point to a custom error page URL.<br><br>

            <table width=100% bgcolor="#ffffcc">
               <tr>
                  <td>
                      <code><pre>

&lt;!-- Web.Config Configuration File --&gt;

&lt;configuration&gt;
    &lt;system.web&gt;
        &lt;customErrors mode=&quot;RemoteOnly&quot; defaultRedirect=&quot;mycustompage.htm&quot;/&gt;
    &lt;/system.web&gt;
&lt;/configuration&gt;</pre></code>

                  </td>
               </tr>
            </table>

            <br>

    </body>
</html>
A_WRAP

            ,
            'txType'                   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected_exception_class' => \Exception::class,
        ];

        yield 'test2' => [
            'input'                    => '',
            'txType'                   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected_exception_class' => \Exception::class,
        ];
    }

    public static function encodeDataProvider(): array
    {
        return [
            [
                'input'    => VakifKatilimPosRequestDataMapperTest::create3DPaymentRequestDataDataProvider()[0]['expected'],
                'expected' => '<?xml version="1.0" encoding="ISO-8859-1"?>
<VPosMessageContract><APIVersion>1.0.0</APIVersion><HashData>sFxxO809/N3Yif4p/js1UKFMRro=</HashData><MerchantId>1</MerchantId><CustomerId>11111</CustomerId><UserName>APIUSER</UserName><InstallmentCount>0</InstallmentCount><Amount>100</Amount><MerchantOrderId>2020110828BC</MerchantOrderId><TransactionSecurity>3</TransactionSecurity><SubMerchantId>0</SubMerchantId><OkUrl>http://localhost/finansbank-payfor/3d/response.php</OkUrl><FailUrl>http://localhost/finansbank-payfor/3d/response.php</FailUrl><AdditionalData><AdditionalDataList><VPosAdditionalData><Key>MD</Key><Data>67YtBfBRTZ0XBKnAHi8c/A==</Data></VPosAdditionalData></AdditionalDataList></AdditionalData></VPosMessageContract>
',
            ],
        ];
    }
}
