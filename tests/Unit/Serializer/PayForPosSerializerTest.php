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
            'input'    => '<?xml version="1.0" encoding="utf-8"?>
<TxnHistoryReport xsi:noNamespaceSchemaLocation="TxnHistoryReport.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <PaymentRequestExtended>
    <PaymentRequest xmlns="http://ocean.smartway.com/">
      <UseExistingDataWhenInserting>false</UseExistingDataWhenInserting>
      <RequestGuid>1000000093565640</RequestGuid>
      <status>1</status>
      <InsertDatetime>2023-12-29T01:09:03</InsertDatetime>
      <lastUpdated>2023122901090396</lastUpdated>
      <MbrId>5</MbrId>
      <MerchantID>085300000009704</MerchantID>
      <OrderId>20231228C01D</OrderId>
      <PaymentSeq>0</PaymentSeq>
      <RequestIp>88.152.8.2</RequestIp>
      <RequestStat>1,10</RequestStat>
      <RequestStartDatetime>20231229010903935</RequestStartDatetime>
      <MpiStartDatetime>0</MpiStartDatetime>
      <MpiEndDatetime>0</MpiEndDatetime>
      <PaymentStartDatetime>20231229010903951</PaymentStartDatetime>
      <PaymentEndDatetime>20231229010903967</PaymentEndDatetime>
      <RequestEndDatetime>20231229010903967</RequestEndDatetime>
      <Pan>9E3EAA293B389C4AD4B22F1B28E15ED0</Pan>
      <Expiry>2501</Expiry>
      <SecureType>NonSecure</SecureType>
      <PurchAmount>1.01</PurchAmount>
      <TxnAmount>1.01</TxnAmount>
      <Exponent>2</Exponent>
      <Currency>949</Currency>
      <UserCode>QNB_API_KULLANICI_3DPAY</UserCode>
      <Description />
      <OkUrl />
      <FailUrl />
      <PayerTxnId />
      <PayerAuthenticationCode />
      <Eci />
      <MD />
      <Hash />
      <TerminalID>VS010481</TerminalID>
      <TxnType>PreAuth</TxnType>
      <TerminalTxnType>2</TerminalTxnType>
      <MOTO>0</MOTO>
      <OrgOrderId />
      <SubMerchantCode />
      <recur_frequency />
      <recur_expiry />
      <CardType>V</CardType>
      <Lang>TR</Lang>
      <Expsign />
      <BonusAmount />
      <InstallmentCount>0</InstallmentCount>
      <Rnd />
      <AlphaCode>TL</AlphaCode>
      <Ecommerce>1</Ecommerce>
      <Accept>*/*</Accept>
      <Agent>Symfony HttpClient/Curl</Agent>
      <MrcCountryCode>792</MrcCountryCode>
      <MrcName>3D PAY TEST ISYERI</MrcName>
      <MerchantHomeUrl>https://vpostest.qnbfinansbank.com/</MerchantHomeUrl>
      <CardHolderName>John Doe</CardHolderName>
      <IrcDet />
      <IrcCode />
      <Version />
      <TxnStatus>Y</TxnStatus>
      <CavvAlg />
      <ParesVerified />
      <ParesSyntaxOk />
      <ErrMsg>Onaylandı</ErrMsg>
      <VendorDet />
      <D3Stat />
      <TxnResult>Success</TxnResult>
      <AuthCode>S74418</AuthCode>
      <HostRefNum />
      <ProcReturnCode>00</ProcReturnCode>
      <ReturnUrl />
      <ErrorData />
      <BatchNo>3322</BatchNo>
      <VoidDate />
      <CardMask>415565******6111</CardMask>
      <ReqId>96705411</ReqId>
      <UsedPoint>0</UsedPoint>
      <SrcType>VPO</SrcType>
      <RefundedAmount>0</RefundedAmount>
      <RefundedPoint>0</RefundedPoint>
      <ReqDate>20231229</ReqDate>
      <SysDate>20231229</SysDate>
      <F11>20764</F11>
      <F37>336301020764</F37>
      <F37_ORG />
      <Mti>0</Mti>
      <Pcode>0</Pcode>
      <F12>10903</F12>
      <F13>1229</F13>
      <F22>812</F22>
      <F25>59</F25>
      <F32 />
      <IsRepeatTxn />
      <CavvResult />
      <VposElapsedTime>32</VposElapsedTime>
      <BankingElapsedTime>0</BankingElapsedTime>
      <SocketElapsedTime>0</SocketElapsedTime>
      <HsmElapsedTime>6</HsmElapsedTime>
      <MpiElapsedTime>0</MpiElapsedTime>
      <hasOrderId>false</hasOrderId>
      <TemplateType>0</TemplateType>
      <HasAddressCount>false</HasAddressCount>
      <IsPaymentFacilitator>false</IsPaymentFacilitator>
      <OrgTxnType />
      <F11_ORG>0</F11_ORG>
      <F12_ORG>0</F12_ORG>
      <F13_ORG />
      <F22_ORG>0</F22_ORG>
      <F25_ORG>0</F25_ORG>
      <MTI_ORG>0</MTI_ORG>
      <DsBrand />
      <IntervalType>0</IntervalType>
      <IntervalDuration>0</IntervalDuration>
      <RepeatCount>0</RepeatCount>
      <CustomerCode />
      <RequestMerchantDomain />
      <RequestClientIp>88.152.8.2</RequestClientIp>
      <ResponseRnd />
      <ResponseHash />
      <BankSpecificRequest>0</BankSpecificRequest>
      <BankInternalResponseCode />
      <BankInternalResponseMessage />
      <BankInternalResponseSubcode />
      <BankInternalResponseSubmessage />
      <BayiKodu />
      <VoidTime>0</VoidTime>
      <VoidUserCode />
      <PaymentLinkId>0</PaymentLinkId>
      <ClientId />
      <IsQR />
      <IsFast />
      <QRRefNo />
      <FASTGonderenKatilimciKodu />
      <FASTAlanKatilimciKodu />
      <FASTReferansNo />
      <FastGonderenIBAN />
      <FASTGonderenAdi />
      <MobileECI />
      <HubConnId />
      <WalletData />
      <Tds2dsTransId />
      <Is3DHost />
      <ArtiTaksit>0</ArtiTaksit>
    </PaymentRequest>
    <ExtraParameters xmlns="http://ocean.smartway.com/">
      <ArrayOfString>
        <string>IsBatchClosed</string>
        <string>True</string>
      </ArrayOfString>
      <ArrayOfString>
        <string>SettlementDate</string>
        <string />
      </ArrayOfString>
    </ExtraParameters>
  </PaymentRequestExtended>
  <PaymentRequestExtended>
    <PaymentRequest xmlns="http://ocean.smartway.com/">
      <UseExistingDataWhenInserting>false</UseExistingDataWhenInserting>
      <RequestGuid>1000000093565641</RequestGuid>
      <status>1</status>
      <InsertDatetime>2023-12-29T01:09:09</InsertDatetime>
      <lastUpdated>2023122901100152</lastUpdated>
      <MbrId>5</MbrId>
      <MerchantID>085300000009704</MerchantID>
      <OrderId>20231228C01D</OrderId>
      <PaymentSeq>0</PaymentSeq>
      <RequestIp>88.152.8.2</RequestIp>
      <RequestStat>1,10</RequestStat>
      <RequestStartDatetime>20231229010909055</RequestStartDatetime>
      <MpiStartDatetime>0</MpiStartDatetime>
      <MpiEndDatetime>0</MpiEndDatetime>
      <PaymentStartDatetime>20231229010909055</PaymentStartDatetime>
      <PaymentEndDatetime>20231229010909086</PaymentEndDatetime>
      <RequestEndDatetime>20231229010909086</RequestEndDatetime>
      <Pan>9E3EAA293B389C4AD4B22F1B28E15ED0</Pan>
      <Expiry>2501</Expiry>
      <SecureType>NonSecure</SecureType>
      <PurchAmount>1.01</PurchAmount>
      <TxnAmount>1.01</TxnAmount>
      <Exponent>2</Exponent>
      <Currency>949</Currency>
      <UserCode>QNB_API_KULLANICI_3DPAY</UserCode>
      <Description />
      <OkUrl />
      <FailUrl />
      <PayerTxnId />
      <PayerAuthenticationCode />
      <Eci />
      <MD />
      <Hash />
      <TerminalID>VS010481</TerminalID>
      <TxnType>PostAuth</TxnType>
      <TerminalTxnType>2</TerminalTxnType>
      <MOTO />
      <OrgOrderId>20231228C01D</OrgOrderId>
      <SubMerchantCode />
      <recur_frequency />
      <recur_expiry />
      <CardType>V</CardType>
      <Lang>TR</Lang>
      <Expsign />
      <BonusAmount />
      <InstallmentCount>0</InstallmentCount>
      <Rnd />
      <AlphaCode>TL</AlphaCode>
      <Ecommerce>1</Ecommerce>
      <Accept>*/*</Accept>
      <Agent>Symfony HttpClient/Curl</Agent>
      <MrcCountryCode>792</MrcCountryCode>
      <MrcName>3D PAY TEST ISYERI</MrcName>
      <MerchantHomeUrl>https://vpostest.qnbfinansbank.com/</MerchantHomeUrl>
      <CardHolderName />
      <IrcDet />
      <IrcCode />
      <Version />
      <TxnStatus>V</TxnStatus>
      <CavvAlg />
      <ParesVerified />
      <ParesSyntaxOk />
      <ErrMsg>Onaylandı</ErrMsg>
      <VendorDet />
      <D3Stat />
      <TxnResult>Success</TxnResult>
      <AuthCode>S47983</AuthCode>
      <HostRefNum />
      <ProcReturnCode>00</ProcReturnCode>
      <ReturnUrl />
      <ErrorData />
      <BatchNo>3322</BatchNo>
      <VoidDate>20231229</VoidDate>
      <CardMask>415565******6111</CardMask>
      <ReqId>96705416</ReqId>
      <UsedPoint>0</UsedPoint>
      <SrcType>VPO</SrcType>
      <RefundedAmount>0</RefundedAmount>
      <RefundedPoint>0</RefundedPoint>
      <ReqDate>20231229</ReqDate>
      <SysDate>20231229</SysDate>
      <F11>20769</F11>
      <F37>336301020769</F37>
      <F37_ORG>336301020764</F37_ORG>
      <Mti>0</Mti>
      <Pcode>0</Pcode>
      <F12>10909</F12>
      <F13>1229</F13>
      <F22>812</F22>
      <F25>59</F25>
      <F32 />
      <IsRepeatTxn />
      <CavvResult />
      <VposElapsedTime>31</VposElapsedTime>
      <BankingElapsedTime>0</BankingElapsedTime>
      <SocketElapsedTime>0</SocketElapsedTime>
      <HsmElapsedTime>5</HsmElapsedTime>
      <MpiElapsedTime>0</MpiElapsedTime>
      <hasOrderId>false</hasOrderId>
      <TemplateType>0</TemplateType>
      <HasAddressCount>false</HasAddressCount>
      <IsPaymentFacilitator>false</IsPaymentFacilitator>
      <OrgTxnType />
      <F11_ORG>20764</F11_ORG>
      <F12_ORG>0</F12_ORG>
      <F13_ORG />
      <F22_ORG>0</F22_ORG>
      <F25_ORG>0</F25_ORG>
      <MTI_ORG>0</MTI_ORG>
      <DsBrand />
      <IntervalType>0</IntervalType>
      <IntervalDuration>0</IntervalDuration>
      <RepeatCount>0</RepeatCount>
      <CustomerCode />
      <RequestMerchantDomain />
      <RequestClientIp>88.152.8.2</RequestClientIp>
      <ResponseRnd />
      <ResponseHash />
      <BankSpecificRequest>0</BankSpecificRequest>
      <BankInternalResponseCode />
      <BankInternalResponseMessage />
      <BankInternalResponseSubcode />
      <BankInternalResponseSubmessage />
      <BayiKodu />
      <VoidTime>11001</VoidTime>
      <VoidUserCode>QNB_API_KULLANICI_3DPAY</VoidUserCode>
      <PaymentLinkId>0</PaymentLinkId>
      <ClientId />
      <IsQR />
      <IsFast />
      <QRRefNo />
      <FASTGonderenKatilimciKodu />
      <FASTAlanKatilimciKodu />
      <FASTReferansNo />
      <FastGonderenIBAN />
      <FASTGonderenAdi />
      <MobileECI />
      <HubConnId />
      <WalletData />
      <Tds2dsTransId />
      <Is3DHost />
      <ArtiTaksit>0</ArtiTaksit>
    </PaymentRequest>
    <ExtraParameters xmlns="http://ocean.smartway.com/">
      <ArrayOfString>
        <string>IsBatchClosed</string>
        <string>True</string>
      </ArrayOfString>
      <ArrayOfString>
        <string>SettlementDate</string>
        <string />
      </ArrayOfString>
    </ExtraParameters>
  </PaymentRequestExtended>
  <PaymentRequestExtended>
    <PaymentRequest xmlns="http://ocean.smartway.com/">
      <UseExistingDataWhenInserting>false</UseExistingDataWhenInserting>
      <RequestGuid>1000000093565642</RequestGuid>
      <status>1</status>
      <InsertDatetime>2023-12-29T01:09:27</InsertDatetime>
      <lastUpdated>2023122901092726</lastUpdated>
      <MbrId>5</MbrId>
      <MerchantID>085300000009704</MerchantID>
      <OrderId>20231228C01D</OrderId>
      <PaymentSeq>96677647</PaymentSeq>
      <RequestIp>88.152.8.2</RequestIp>
      <RequestStat>1,10</RequestStat>
      <RequestStartDatetime>20231229010927230</RequestStartDatetime>
      <MpiStartDatetime>0</MpiStartDatetime>
      <MpiEndDatetime>0</MpiEndDatetime>
      <PaymentStartDatetime>20231229010927246</PaymentStartDatetime>
      <PaymentEndDatetime>20231229010927261</PaymentEndDatetime>
      <RequestEndDatetime>20231229010927261</RequestEndDatetime>
      <Pan>9E3EAA293B389C4AD4B22F1B28E15ED0</Pan>
      <Expiry>2501</Expiry>
      <SecureType>NonSecure</SecureType>
      <PurchAmount>1.01</PurchAmount>
      <TxnAmount>1.01</TxnAmount>
      <Exponent>2</Exponent>
      <Currency>949</Currency>
      <UserCode>QNB_API_KULLANICI_3DPAY</UserCode>
      <Description />
      <OkUrl />
      <FailUrl />
      <PayerTxnId />
      <PayerAuthenticationCode />
      <Eci />
      <MD />
      <Hash />
      <TerminalID>VS010481</TerminalID>
      <TxnType>Refund</TxnType>
      <TerminalTxnType>2</TerminalTxnType>
      <MOTO />
      <OrgOrderId>20231228C01D</OrgOrderId>
      <SubMerchantCode />
      <recur_frequency />
      <recur_expiry />
      <CardType>V</CardType>
      <Lang>TR</Lang>
      <Expsign />
      <BonusAmount />
      <InstallmentCount>0</InstallmentCount>
      <Rnd />
      <AlphaCode>TL</AlphaCode>
      <Ecommerce>1</Ecommerce>
      <Accept>*/*</Accept>
      <Agent>Symfony HttpClient/Curl</Agent>
      <MrcCountryCode>792</MrcCountryCode>
      <MrcName>3D PAY TEST ISYERI</MrcName>
      <MerchantHomeUrl>https://vpostest.qnbfinansbank.com/</MerchantHomeUrl>
      <CardHolderName />
      <IrcDet>Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.</IrcDet>
      <IrcCode>99962</IrcCode>
      <Version />
      <TxnStatus>N</TxnStatus>
      <CavvAlg />
      <ParesVerified />
      <ParesSyntaxOk />
      <ErrMsg>Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.</ErrMsg>
      <VendorDet />
      <D3Stat />
      <TxnResult>Failed</TxnResult>
      <AuthCode>S47983</AuthCode>
      <HostRefNum />
      <ProcReturnCode>V014</ProcReturnCode>
      <ReturnUrl />
      <ErrorData />
      <BatchNo>3322</BatchNo>
      <VoidDate />
      <CardMask>415565******6111</CardMask>
      <ReqId>96705431</ReqId>
      <UsedPoint>0</UsedPoint>
      <SrcType>VPO</SrcType>
      <RefundedAmount>1.01</RefundedAmount>
      <RefundedPoint>0</RefundedPoint>
      <ReqDate>20231229</ReqDate>
      <SysDate>20231229</SysDate>
      <F11>20784</F11>
      <F37>336301020784</F37>
      <F37_ORG>336301020769</F37_ORG>
      <Mti>0</Mti>
      <Pcode>0</Pcode>
      <F12>10927</F12>
      <F13>1229</F13>
      <F22>812</F22>
      <F25>59</F25>
      <F32 />
      <IsRepeatTxn />
      <CavvResult />
      <VposElapsedTime>31</VposElapsedTime>
      <BankingElapsedTime>0</BankingElapsedTime>
      <SocketElapsedTime>0</SocketElapsedTime>
      <HsmElapsedTime>6</HsmElapsedTime>
      <MpiElapsedTime>0</MpiElapsedTime>
      <hasOrderId>false</hasOrderId>
      <TemplateType>0</TemplateType>
      <HasAddressCount>false</HasAddressCount>
      <IsPaymentFacilitator>false</IsPaymentFacilitator>
      <OrgTxnType>PostAuth</OrgTxnType>
      <F11_ORG>20769</F11_ORG>
      <F12_ORG>10909</F12_ORG>
      <F13_ORG>1229</F13_ORG>
      <F22_ORG>812</F22_ORG>
      <F25_ORG>59</F25_ORG>
      <MTI_ORG>0</MTI_ORG>
      <DsBrand />
      <IntervalType>0</IntervalType>
      <IntervalDuration>0</IntervalDuration>
      <RepeatCount>0</RepeatCount>
      <CustomerCode />
      <RequestMerchantDomain />
      <RequestClientIp>88.152.8.2</RequestClientIp>
      <ResponseRnd />
      <ResponseHash />
      <BankSpecificRequest>0</BankSpecificRequest>
      <BankInternalResponseCode />
      <BankInternalResponseMessage />
      <BankInternalResponseSubcode />
      <BankInternalResponseSubmessage />
      <BayiKodu />
      <VoidTime>0</VoidTime>
      <VoidUserCode />
      <PaymentLinkId>0</PaymentLinkId>
      <ClientId />
      <IsQR />
      <IsFast />
      <QRRefNo />
      <FASTGonderenKatilimciKodu />
      <FASTAlanKatilimciKodu />
      <FASTReferansNo />
      <FastGonderenIBAN />
      <FASTGonderenAdi />
      <MobileECI />
      <HubConnId />
      <WalletData />
      <Tds2dsTransId />
      <Is3DHost />
      <ArtiTaksit>0</ArtiTaksit>
    </PaymentRequest>
    <ExtraParameters xmlns="http://ocean.smartway.com/">
      <ArrayOfString>
        <string>IsBatchClosed</string>
        <string>True</string>
      </ArrayOfString>
      <ArrayOfString>
        <string>SettlementDate</string>
        <string />
      </ArrayOfString>
    </ExtraParameters>
  </PaymentRequestExtended>
  <PaymentRequestExtended>
    <PaymentRequest xmlns="http://ocean.smartway.com/">
      <UseExistingDataWhenInserting>false</UseExistingDataWhenInserting>
      <RequestGuid>1000000093565668</RequestGuid>
      <status>1</status>
      <InsertDatetime>2023-12-29T01:10:01</InsertDatetime>
      <lastUpdated>2023122901100153</lastUpdated>
      <MbrId>5</MbrId>
      <MerchantID>085300000009704</MerchantID>
      <OrderId>20231228C01D</OrderId>
      <PaymentSeq>0</PaymentSeq>
      <RequestIp>88.152.8.2</RequestIp>
      <RequestStat>1,10</RequestStat>
      <RequestStartDatetime>20231229011001458</RequestStartDatetime>
      <MpiStartDatetime>0</MpiStartDatetime>
      <MpiEndDatetime>0</MpiEndDatetime>
      <PaymentStartDatetime>20231229011001489</PaymentStartDatetime>
      <PaymentEndDatetime>20231229011001536</PaymentEndDatetime>
      <RequestEndDatetime>20231229011001536</RequestEndDatetime>
      <Pan>9E3EAA293B389C4AD4B22F1B28E15ED0</Pan>
      <Expiry>2501</Expiry>
      <SecureType>NonSecure</SecureType>
      <PurchAmount>1.01</PurchAmount>
      <TxnAmount>1.01</TxnAmount>
      <Exponent>2</Exponent>
      <Currency>949</Currency>
      <UserCode>QNB_API_KULLANICI_3DPAY</UserCode>
      <Description />
      <OkUrl />
      <FailUrl />
      <PayerTxnId />
      <PayerAuthenticationCode />
      <Eci />
      <MD />
      <Hash />
      <TerminalID>VS010481</TerminalID>
      <TxnType>Void</TxnType>
      <TerminalTxnType>2</TerminalTxnType>
      <MOTO />
      <OrgOrderId>20231228C01D</OrgOrderId>
      <SubMerchantCode />
      <recur_frequency />
      <recur_expiry />
      <CardType>V</CardType>
      <Lang>TR</Lang>
      <Expsign />
      <BonusAmount />
      <InstallmentCount>0</InstallmentCount>
      <Rnd />
      <AlphaCode>TL</AlphaCode>
      <Ecommerce>1</Ecommerce>
      <Accept>*/*</Accept>
      <Agent>Symfony HttpClient/Curl</Agent>
      <MrcCountryCode>792</MrcCountryCode>
      <MrcName>3D PAY TEST ISYERI</MrcName>
      <MerchantHomeUrl>https://vpostest.qnbfinansbank.com/</MerchantHomeUrl>
      <CardHolderName />
      <IrcDet />
      <IrcCode />
      <Version />
      <TxnStatus>Y</TxnStatus>
      <CavvAlg />
      <ParesVerified />
      <ParesSyntaxOk />
      <ErrMsg>Onaylandı</ErrMsg>
      <VendorDet />
      <D3Stat />
      <TxnResult>Success</TxnResult>
      <AuthCode>S74990</AuthCode>
      <HostRefNum />
      <ProcReturnCode>00</ProcReturnCode>
      <ReturnUrl />
      <ErrorData />
      <BatchNo>3322</BatchNo>
      <VoidDate />
      <CardMask>415565******6111</CardMask>
      <ReqId>96705532</ReqId>
      <UsedPoint>0</UsedPoint>
      <SrcType>VPO</SrcType>
      <RefundedAmount>0</RefundedAmount>
      <RefundedPoint>0</RefundedPoint>
      <ReqDate>20231229</ReqDate>
      <SysDate>20231229</SysDate>
      <F11>20884</F11>
      <F37>336301020884</F37>
      <F37_ORG>336301020764</F37_ORG>
      <Mti>0</Mti>
      <Pcode>0</Pcode>
      <F12>11001</F12>
      <F13>1229</F13>
      <F22>812</F22>
      <F25>59</F25>
      <F32 />
      <IsRepeatTxn />
      <CavvResult />
      <VposElapsedTime>78</VposElapsedTime>
      <BankingElapsedTime>0</BankingElapsedTime>
      <SocketElapsedTime>0</SocketElapsedTime>
      <HsmElapsedTime>9</HsmElapsedTime>
      <MpiElapsedTime>0</MpiElapsedTime>
      <hasOrderId>false</hasOrderId>
      <TemplateType>0</TemplateType>
      <HasAddressCount>false</HasAddressCount>
      <IsPaymentFacilitator>false</IsPaymentFacilitator>
      <OrgTxnType>PostAuth</OrgTxnType>
      <F11_ORG>20764</F11_ORG>
      <F12_ORG>10909</F12_ORG>
      <F13_ORG>1229</F13_ORG>
      <F22_ORG>812</F22_ORG>
      <F25_ORG>59</F25_ORG>
      <MTI_ORG>0</MTI_ORG>
      <DsBrand />
      <IntervalType>0</IntervalType>
      <IntervalDuration>0</IntervalDuration>
      <RepeatCount>0</RepeatCount>
      <CustomerCode />
      <RequestMerchantDomain />
      <RequestClientIp>88.152.8.2</RequestClientIp>
      <ResponseRnd />
      <ResponseHash />
      <BankSpecificRequest>0</BankSpecificRequest>
      <BankInternalResponseCode />
      <BankInternalResponseMessage />
      <BankInternalResponseSubcode />
      <BankInternalResponseSubmessage />
      <BayiKodu />
      <VoidTime>0</VoidTime>
      <VoidUserCode />
      <PaymentLinkId>0</PaymentLinkId>
      <ClientId />
      <IsQR />
      <IsFast />
      <QRRefNo />
      <FASTGonderenKatilimciKodu />
      <FASTAlanKatilimciKodu />
      <FASTReferansNo />
      <FastGonderenIBAN />
      <FASTGonderenAdi />
      <MobileECI />
      <HubConnId />
      <WalletData />
      <Tds2dsTransId />
      <Is3DHost />
      <ArtiTaksit>0</ArtiTaksit>
    </PaymentRequest>
    <ExtraParameters xmlns="http://ocean.smartway.com/">
      <ArrayOfString>
        <string>IsBatchClosed</string>
        <string>True</string>
      </ArrayOfString>
      <ArrayOfString>
        <string>SettlementDate</string>
        <string />
      </ArrayOfString>
    </ExtraParameters>
  </PaymentRequestExtended>
</TxnHistoryReport>',
            'txType'   => PosInterface::TX_TYPE_ORDER_HISTORY,
            'expected' => [
                '@xsi:noNamespaceSchemaLocation' => 'TxnHistoryReport.xsd',
                'PaymentRequestExtended' => [
                    [
                        'PaymentRequest' => [
                            'UseExistingDataWhenInserting' => 'false',
                            'RequestGuid' => '1000000093565640',
                            'status' => '1',
                            'InsertDatetime' => '2023-12-29T01:09:03',
                            'lastUpdated' => '2023122901090396',
                            'MbrId' => '5',
                            'MerchantID' => '085300000009704',
                            'OrderId' => '20231228C01D',
                            'PaymentSeq' => '0',
                            'RequestIp' => '88.152.8.2',
                            'RequestStat' => '1,10',
                            'RequestStartDatetime' => '20231229010903935',
                            'MpiStartDatetime' => '0',
                            'MpiEndDatetime' => '0',
                            'PaymentStartDatetime' => '20231229010903951',
                            'PaymentEndDatetime' => '20231229010903967',
                            'RequestEndDatetime' => '20231229010903967',
                            'Pan' => '9E3EAA293B389C4AD4B22F1B28E15ED0',
                            'Expiry' => '2501',
                            'SecureType' => 'NonSecure',
                            'PurchAmount' => '1.01',
                            'TxnAmount' => '1.01',
                            'Exponent' => '2',
                            'Currency' => '949',
                            'UserCode' => 'QNB_API_KULLANICI_3DPAY',
                            'Description' => '',
                            'OkUrl' => '',
                            'FailUrl' => '',
                            'PayerTxnId' => '',
                            'PayerAuthenticationCode' => '',
                            'Eci' => '',
                            'MD' => '',
                            'Hash' => '',
                            'TerminalID' => 'VS010481',
                            'TxnType' => 'PreAuth',
                            'TerminalTxnType' => '2',
                            'MOTO' => '0',
                            'OrgOrderId' => '',
                            'SubMerchantCode' => '',
                            'recur_frequency' => '',
                            'recur_expiry' => '',
                            'CardType' => 'V',
                            'Lang' => 'TR',
                            'Expsign' => '',
                            'BonusAmount' => '',
                            'InstallmentCount' => '0',
                            'Rnd' => '',
                            'AlphaCode' => 'TL',
                            'Ecommerce' => '1',
                            'Accept' => '*/*',
                            'Agent' => 'Symfony HttpClient/Curl',
                            'MrcCountryCode' => '792',
                            'MrcName' => '3D PAY TEST ISYERI',
                            'MerchantHomeUrl' => 'https://vpostest.qnbfinansbank.com/',
                            'CardHolderName' => 'John Doe',
                            'IrcDet' => '',
                            'IrcCode' => '',
                            'Version' => '',
                            'TxnStatus' => 'Y',
                            'CavvAlg' => '',
                            'ParesVerified' => '',
                            'ParesSyntaxOk' => '',
                            'ErrMsg' => 'Onaylandı',
                            'VendorDet' => '',
                            'D3Stat' => '',
                            'TxnResult' => 'Success',
                            'AuthCode' => 'S74418',
                            'HostRefNum' => '',
                            'ProcReturnCode' => '00',
                            'ReturnUrl' => '',
                            'ErrorData' => '',
                            'BatchNo' => '3322',
                            'VoidDate' => '',
                            'CardMask' => '415565******6111',
                            'ReqId' => '96705411',
                            'UsedPoint' => '0',
                            'SrcType' => 'VPO',
                            'RefundedAmount' => '0',
                            'RefundedPoint' => '0',
                            'ReqDate' => '20231229',
                            'SysDate' => '20231229',
                            'F11' => '20764',
                            'F37' => '336301020764',
                            'F37_ORG' => '',
                            'Mti' => '0',
                            'Pcode' => '0',
                            'F12' => '10903',
                            'F13' => '1229',
                            'F22' => '812',
                            'F25' => '59',
                            'F32' => '',
                            'IsRepeatTxn' => '',
                            'CavvResult' => '',
                            'VposElapsedTime' => '32',
                            'BankingElapsedTime' => '0',
                            'SocketElapsedTime' => '0',
                            'HsmElapsedTime' => '6',
                            'MpiElapsedTime' => '0',
                            'hasOrderId' => 'false',
                            'TemplateType' => '0',
                            'HasAddressCount' => 'false',
                            'IsPaymentFacilitator' => 'false',
                            'OrgTxnType' => '',
                            'F11_ORG' => '0',
                            'F12_ORG' => '0',
                            'F13_ORG' => '',
                            'F22_ORG' => '0',
                            'F25_ORG' => '0',
                            'MTI_ORG' => '0',
                            'DsBrand' => '',
                            'IntervalType' => '0',
                            'IntervalDuration' => '0',
                            'RepeatCount' => '0',
                            'CustomerCode' => '',
                            'RequestMerchantDomain' => '',
                            'RequestClientIp' => '88.152.8.2',
                            'ResponseRnd' => '',
                            'ResponseHash' => '',
                            'BankSpecificRequest' => '0',
                            'BankInternalResponseCode' => '',
                            'BankInternalResponseMessage' => '',
                            'BankInternalResponseSubcode' => '',
                            'BankInternalResponseSubmessage' => '',
                            'BayiKodu' => '',
                            'VoidTime' => '0',
                            'VoidUserCode' => '',
                            'PaymentLinkId' => '0',
                            'ClientId' => '',
                            'IsQR' => '',
                            'IsFast' => '',
                            'QRRefNo' => '',
                            'FASTGonderenKatilimciKodu' => '',
                            'FASTAlanKatilimciKodu' => '',
                            'FASTReferansNo' => '',
                            'FastGonderenIBAN' => '',
                            'FASTGonderenAdi' => '',
                            'MobileECI' => '',
                            'HubConnId' => '',
                            'WalletData' => '',
                            'Tds2dsTransId' => '',
                            'Is3DHost' => '',
                            'ArtiTaksit' => '0',
                        ],

                        'ExtraParameters' => [
                            'ArrayOfString' => [
                                0 => [
                                    'string' => [
                                        0 => 'IsBatchClosed',
                                        1 => 'True',
                                    ],

                                ],

                                1 => [
                                    'string' => [
                                        0 => 'SettlementDate',
                                        1 => '',
                                    ],

                                ],

                            ],

                        ],

                    ],

                    1 => [
                        'PaymentRequest' => [
                            'UseExistingDataWhenInserting' => 'false',
                            'RequestGuid' => '1000000093565641',
                            'status' => '1',
                            'InsertDatetime' => '2023-12-29T01:09:09',
                            'lastUpdated' => '2023122901100152',
                            'MbrId' => '5',
                            'MerchantID' => '085300000009704',
                            'OrderId' => '20231228C01D',
                            'PaymentSeq' => '0',
                            'RequestIp' => '88.152.8.2',
                            'RequestStat' => '1,10',
                            'RequestStartDatetime' => '20231229010909055',
                            'MpiStartDatetime' => '0',
                            'MpiEndDatetime' => '0',
                            'PaymentStartDatetime' => '20231229010909055',
                            'PaymentEndDatetime' => '20231229010909086',
                            'RequestEndDatetime' => '20231229010909086',
                            'Pan' => '9E3EAA293B389C4AD4B22F1B28E15ED0',
                            'Expiry' => '2501',
                            'SecureType' => 'NonSecure',
                            'PurchAmount' => '1.01',
                            'TxnAmount' => '1.01',
                            'Exponent' => '2',
                            'Currency' => '949',
                            'UserCode' => 'QNB_API_KULLANICI_3DPAY',
                            'Description' => '',
                            'OkUrl' => '',
                            'FailUrl' => '',
                            'PayerTxnId' => '',
                            'PayerAuthenticationCode' => '',
                            'Eci' => '',
                            'MD' => '',
                            'Hash' => '',
                            'TerminalID' => 'VS010481',
                            'TxnType' => 'PostAuth',
                            'TerminalTxnType' => '2',
                            'MOTO' => '',
                            'OrgOrderId' => '20231228C01D',
                            'SubMerchantCode' => '',
                            'recur_frequency' => '',
                            'recur_expiry' => '',
                            'CardType' => 'V',
                            'Lang' => 'TR',
                            'Expsign' => '',
                            'BonusAmount' => '',
                            'InstallmentCount' => '0',
                            'Rnd' => '',
                            'AlphaCode' => 'TL',
                            'Ecommerce' => '1',
                            'Accept' => '*/*',
                            'Agent' => 'Symfony HttpClient/Curl',
                            'MrcCountryCode' => '792',
                            'MrcName' => '3D PAY TEST ISYERI',
                            'MerchantHomeUrl' => 'https://vpostest.qnbfinansbank.com/',
                            'CardHolderName' => '',
                            'IrcDet' => '',
                            'IrcCode' => '',
                            'Version' => '',
                            'TxnStatus' => 'V',
                            'CavvAlg' => '',
                            'ParesVerified' => '',
                            'ParesSyntaxOk' => '',
                            'ErrMsg' => 'Onaylandı',
                            'VendorDet' => '',
                            'D3Stat' => '',
                            'TxnResult' => 'Success',
                            'AuthCode' => 'S47983',
                            'HostRefNum' => '',
                            'ProcReturnCode' => '00',
                            'ReturnUrl' => '',
                            'ErrorData' => '',
                            'BatchNo' => '3322',
                            'VoidDate' => '20231229',
                            'CardMask' => '415565******6111',
                            'ReqId' => '96705416',
                            'UsedPoint' => '0',
                            'SrcType' => 'VPO',
                            'RefundedAmount' => '0',
                            'RefundedPoint' => '0',
                            'ReqDate' => '20231229',
                            'SysDate' => '20231229',
                            'F11' => '20769',
                            'F37' => '336301020769',
                            'F37_ORG' => '336301020764',
                            'Mti' => '0',
                            'Pcode' => '0',
                            'F12' => '10909',
                            'F13' => '1229',
                            'F22' => '812',
                            'F25' => '59',
                            'F32' => '',
                            'IsRepeatTxn' => '',
                            'CavvResult' => '',
                            'VposElapsedTime' => '31',
                            'BankingElapsedTime' => '0',
                            'SocketElapsedTime' => '0',
                            'HsmElapsedTime' => '5',
                            'MpiElapsedTime' => '0',
                            'hasOrderId' => 'false',
                            'TemplateType' => '0',
                            'HasAddressCount' => 'false',
                            'IsPaymentFacilitator' => 'false',
                            'OrgTxnType' => '',
                            'F11_ORG' => '20764',
                            'F12_ORG' => '0',
                            'F13_ORG' => '',
                            'F22_ORG' => '0',
                            'F25_ORG' => '0',
                            'MTI_ORG' => '0',
                            'DsBrand' => '',
                            'IntervalType' => '0',
                            'IntervalDuration' => '0',
                            'RepeatCount' => '0',
                            'CustomerCode' => '',
                            'RequestMerchantDomain' => '',
                            'RequestClientIp' => '88.152.8.2',
                            'ResponseRnd' => '',
                            'ResponseHash' => '',
                            'BankSpecificRequest' => '0',
                            'BankInternalResponseCode' => '',
                            'BankInternalResponseMessage' => '',
                            'BankInternalResponseSubcode' => '',
                            'BankInternalResponseSubmessage' => '',
                            'BayiKodu' => '',
                            'VoidTime' => '11001',
                            'VoidUserCode' => 'QNB_API_KULLANICI_3DPAY',
                            'PaymentLinkId' => '0',
                            'ClientId' => '',
                            'IsQR' => '',
                            'IsFast' => '',
                            'QRRefNo' => '',
                            'FASTGonderenKatilimciKodu' => '',
                            'FASTAlanKatilimciKodu' => '',
                            'FASTReferansNo' => '',
                            'FastGonderenIBAN' => '',
                            'FASTGonderenAdi' => '',
                            'MobileECI' => '',
                            'HubConnId' => '',
                            'WalletData' => '',
                            'Tds2dsTransId' => '',
                            'Is3DHost' => '',
                            'ArtiTaksit' => '0',
                        ],

                        'ExtraParameters' => [
                            'ArrayOfString' => [
                                0 => [
                                    'string' => [
                                        0 => 'IsBatchClosed',
                                        1 => 'True',
                                    ],

                                ],

                                1 => [
                                    'string' => [
                                        0 => 'SettlementDate',
                                        1 => '',
                                    ],

                                ],

                            ],

                        ],

                    ],

                    2 => [
                        'PaymentRequest' => [
                            'UseExistingDataWhenInserting' => 'false',
                            'RequestGuid' => '1000000093565642',
                            'status' => '1',
                            'InsertDatetime' => '2023-12-29T01:09:27',
                            'lastUpdated' => '2023122901092726',
                            'MbrId' => '5',
                            'MerchantID' => '085300000009704',
                            'OrderId' => '20231228C01D',
                            'PaymentSeq' => '96677647',
                            'RequestIp' => '88.152.8.2',
                            'RequestStat' => '1,10',
                            'RequestStartDatetime' => '20231229010927230',
                            'MpiStartDatetime' => '0',
                            'MpiEndDatetime' => '0',
                            'PaymentStartDatetime' => '20231229010927246',
                            'PaymentEndDatetime' => '20231229010927261',
                            'RequestEndDatetime' => '20231229010927261',
                            'Pan' => '9E3EAA293B389C4AD4B22F1B28E15ED0',
                            'Expiry' => '2501',
                            'SecureType' => 'NonSecure',
                            'PurchAmount' => '1.01',
                            'TxnAmount' => '1.01',
                            'Exponent' => '2',
                            'Currency' => '949',
                            'UserCode' => 'QNB_API_KULLANICI_3DPAY',
                            'Description' => '',
                            'OkUrl' => '',
                            'FailUrl' => '',
                            'PayerTxnId' => '',
                            'PayerAuthenticationCode' => '',
                            'Eci' => '',
                            'MD' => '',
                            'Hash' => '',
                            'TerminalID' => 'VS010481',
                            'TxnType' => 'Refund',
                            'TerminalTxnType' => '2',
                            'MOTO' => '',
                            'OrgOrderId' => '20231228C01D',
                            'SubMerchantCode' => '',
                            'recur_frequency' => '',
                            'recur_expiry' => '',
                            'CardType' => 'V',
                            'Lang' => 'TR',
                            'Expsign' => '',
                            'BonusAmount' => '',
                            'InstallmentCount' => '0',
                            'Rnd' => '',
                            'AlphaCode' => 'TL',
                            'Ecommerce' => '1',
                            'Accept' => '*/*',
                            'Agent' => 'Symfony HttpClient/Curl',
                            'MrcCountryCode' => '792',
                            'MrcName' => '3D PAY TEST ISYERI',
                            'MerchantHomeUrl' => 'https://vpostest.qnbfinansbank.com/',
                            'CardHolderName' => '',
                            'IrcDet' => 'Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.',
                            'IrcCode' => '99962',
                            'Version' => '',
                            'TxnStatus' => 'N',
                            'CavvAlg' => '',
                            'ParesVerified' => '',
                            'ParesSyntaxOk' => '',
                            'ErrMsg' => 'Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.',
                            'VendorDet' => '',
                            'D3Stat' => '',
                            'TxnResult' => 'Failed',
                            'AuthCode' => 'S47983',
                            'HostRefNum' => '',
                            'ProcReturnCode' => 'V014',
                            'ReturnUrl' => '',
                            'ErrorData' => '',
                            'BatchNo' => '3322',
                            'VoidDate' => '',
                            'CardMask' => '415565******6111',
                            'ReqId' => '96705431',
                            'UsedPoint' => '0',
                            'SrcType' => 'VPO',
                            'RefundedAmount' => '1.01',
                            'RefundedPoint' => '0',
                            'ReqDate' => '20231229',
                            'SysDate' => '20231229',
                            'F11' => '20784',
                            'F37' => '336301020784',
                            'F37_ORG' => '336301020769',
                            'Mti' => '0',
                            'Pcode' => '0',
                            'F12' => '10927',
                            'F13' => '1229',
                            'F22' => '812',
                            'F25' => '59',
                            'F32' => '',
                            'IsRepeatTxn' => '',
                            'CavvResult' => '',
                            'VposElapsedTime' => '31',
                            'BankingElapsedTime' => '0',
                            'SocketElapsedTime' => '0',
                            'HsmElapsedTime' => '6',
                            'MpiElapsedTime' => '0',
                            'hasOrderId' => 'false',
                            'TemplateType' => '0',
                            'HasAddressCount' => 'false',
                            'IsPaymentFacilitator' => 'false',
                            'OrgTxnType' => 'PostAuth',
                            'F11_ORG' => '20769',
                            'F12_ORG' => '10909',
                            'F13_ORG' => '1229',
                            'F22_ORG' => '812',
                            'F25_ORG' => '59',
                            'MTI_ORG' => '0',
                            'DsBrand' => '',
                            'IntervalType' => '0',
                            'IntervalDuration' => '0',
                            'RepeatCount' => '0',
                            'CustomerCode' => '',
                            'RequestMerchantDomain' => '',
                            'RequestClientIp' => '88.152.8.2',
                            'ResponseRnd' => '',
                            'ResponseHash' => '',
                            'BankSpecificRequest' => '0',
                            'BankInternalResponseCode' => '',
                            'BankInternalResponseMessage' => '',
                            'BankInternalResponseSubcode' => '',
                            'BankInternalResponseSubmessage' => '',
                            'BayiKodu' => '',
                            'VoidTime' => '0',
                            'VoidUserCode' => '',
                            'PaymentLinkId' => '0',
                            'ClientId' => '',
                            'IsQR' => '',
                            'IsFast' => '',
                            'QRRefNo' => '',
                            'FASTGonderenKatilimciKodu' => '',
                            'FASTAlanKatilimciKodu' => '',
                            'FASTReferansNo' => '',
                            'FastGonderenIBAN' => '',
                            'FASTGonderenAdi' => '',
                            'MobileECI' => '',
                            'HubConnId' => '',
                            'WalletData' => '',
                            'Tds2dsTransId' => '',
                            'Is3DHost' => '',
                            'ArtiTaksit' => '0',
                        ],

                        'ExtraParameters' => [
                            'ArrayOfString' => [
                                0 => [
                                    'string' => [
                                        0 => 'IsBatchClosed',
                                        1 => 'True',
                                    ],

                                ],

                                1 => [
                                    'string' => [
                                        0 => 'SettlementDate',
                                        1 => '',
                                    ],

                                ],

                            ],

                        ],

                    ],

                    3 => [
                        'PaymentRequest' => [
                            'UseExistingDataWhenInserting' => 'false',
                            'RequestGuid' => '1000000093565668',
                            'status' => '1',
                            'InsertDatetime' => '2023-12-29T01:10:01',
                            'lastUpdated' => '2023122901100153',
                            'MbrId' => '5',
                            'MerchantID' => '085300000009704',
                            'OrderId' => '20231228C01D',
                            'PaymentSeq' => '0',
                            'RequestIp' => '88.152.8.2',
                            'RequestStat' => '1,10',
                            'RequestStartDatetime' => '20231229011001458',
                            'MpiStartDatetime' => '0',
                            'MpiEndDatetime' => '0',
                            'PaymentStartDatetime' => '20231229011001489',
                            'PaymentEndDatetime' => '20231229011001536',
                            'RequestEndDatetime' => '20231229011001536',
                            'Pan' => '9E3EAA293B389C4AD4B22F1B28E15ED0',
                            'Expiry' => '2501',
                            'SecureType' => 'NonSecure',
                            'PurchAmount' => '1.01',
                            'TxnAmount' => '1.01',
                            'Exponent' => '2',
                            'Currency' => '949',
                            'UserCode' => 'QNB_API_KULLANICI_3DPAY',
                            'Description' => '',
                            'OkUrl' => '',
                            'FailUrl' => '',
                            'PayerTxnId' => '',
                            'PayerAuthenticationCode' => '',
                            'Eci' => '',
                            'MD' => '',
                            'Hash' => '',
                            'TerminalID' => 'VS010481',
                            'TxnType' => 'Void',
                            'TerminalTxnType' => '2',
                            'MOTO' => '',
                            'OrgOrderId' => '20231228C01D',
                            'SubMerchantCode' => '',
                            'recur_frequency' => '',
                            'recur_expiry' => '',
                            'CardType' => 'V',
                            'Lang' => 'TR',
                            'Expsign' => '',
                            'BonusAmount' => '',
                            'InstallmentCount' => '0',
                            'Rnd' => '',
                            'AlphaCode' => 'TL',
                            'Ecommerce' => '1',
                            'Accept' => '*/*',
                            'Agent' => 'Symfony HttpClient/Curl',
                            'MrcCountryCode' => '792',
                            'MrcName' => '3D PAY TEST ISYERI',
                            'MerchantHomeUrl' => 'https://vpostest.qnbfinansbank.com/',
                            'CardHolderName' => '',
                            'IrcDet' => '',
                            'IrcCode' => '',
                            'Version' => '',
                            'TxnStatus' => 'Y',
                            'CavvAlg' => '',
                            'ParesVerified' => '',
                            'ParesSyntaxOk' => '',
                            'ErrMsg' => 'Onaylandı',
                            'VendorDet' => '',
                            'D3Stat' => '',
                            'TxnResult' => 'Success',
                            'AuthCode' => 'S74990',
                            'HostRefNum' => '',
                            'ProcReturnCode' => '00',
                            'ReturnUrl' => '',
                            'ErrorData' => '',
                            'BatchNo' => '3322',
                            'VoidDate' => '',
                            'CardMask' => '415565******6111',
                            'ReqId' => '96705532',
                            'UsedPoint' => '0',
                            'SrcType' => 'VPO',
                            'RefundedAmount' => '0',
                            'RefundedPoint' => '0',
                            'ReqDate' => '20231229',
                            'SysDate' => '20231229',
                            'F11' => '20884',
                            'F37' => '336301020884',
                            'F37_ORG' => '336301020764',
                            'Mti' => '0',
                            'Pcode' => '0',
                            'F12' => '11001',
                            'F13' => '1229',
                            'F22' => '812',
                            'F25' => '59',
                            'F32' => '',
                            'IsRepeatTxn' => '',
                            'CavvResult' => '',
                            'VposElapsedTime' => '78',
                            'BankingElapsedTime' => '0',
                            'SocketElapsedTime' => '0',
                            'HsmElapsedTime' => '9',
                            'MpiElapsedTime' => '0',
                            'hasOrderId' => 'false',
                            'TemplateType' => '0',
                            'HasAddressCount' => 'false',
                            'IsPaymentFacilitator' => 'false',
                            'OrgTxnType' => 'PostAuth',
                            'F11_ORG' => '20764',
                            'F12_ORG' => '10909',
                            'F13_ORG' => '1229',
                            'F22_ORG' => '812',
                            'F25_ORG' => '59',
                            'MTI_ORG' => '0',
                            'DsBrand' => '',
                            'IntervalType' => '0',
                            'IntervalDuration' => '0',
                            'RepeatCount' => '0',
                            'CustomerCode' => '',
                            'RequestMerchantDomain' => '',
                            'RequestClientIp' => '88.152.8.2',
                            'ResponseRnd' => '',
                            'ResponseHash' => '',
                            'BankSpecificRequest' => '0',
                            'BankInternalResponseCode' => '',
                            'BankInternalResponseMessage' => '',
                            'BankInternalResponseSubcode' => '',
                            'BankInternalResponseSubmessage' => '',
                            'BayiKodu' => '',
                            'VoidTime' => '0',
                            'VoidUserCode' => '',
                            'PaymentLinkId' => '0',
                            'ClientId' => '',
                            'IsQR' => '',
                            'IsFast' => '',
                            'QRRefNo' => '',
                            'FASTGonderenKatilimciKodu' => '',
                            'FASTAlanKatilimciKodu' => '',
                            'FASTReferansNo' => '',
                            'FastGonderenIBAN' => '',
                            'FASTGonderenAdi' => '',
                            'MobileECI' => '',
                            'HubConnId' => '',
                            'WalletData' => '',
                            'Tds2dsTransId' => '',
                            'Is3DHost' => '',
                            'ArtiTaksit' => '0',
                        ],

                        'ExtraParameters' => [
                            'ArrayOfString' => [
                                0 => [
                                    'string' => [
                                        0 => 'IsBatchClosed',
                                        1 => 'True',
                                    ],

                                ],

                                1 => [
                                    'string' => [
                                        0 => 'SettlementDate',
                                        1 => '',
                                    ],

                                ],

                            ],

                        ],

                    ],

                ],
                '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            ],
        ];
    }
}
