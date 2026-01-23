<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\KuveytPosSerializer;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\KuveytPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;

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
    public function testEncode(array $data, string $txType, $expected): void
    {
        $result = $this->serializer->encode($data, $txType);
        if (is_string($expected)) {
            $expected = str_replace(["\r"], '', $expected);
        }

        $this->assertSame($expected, $result);
    }

    public function testEncodeException(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode(['abc' => 1], PosInterface::TX_TYPE_HISTORY);

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->serializer->encode(['abc' => 1], PosInterface::TX_TYPE_ORDER_HISTORY);
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
     * @dataProvider decodeJsonDataProvider
     */
    public function testDecodeJson(string $input, string $txType, array $expected): void
    {
        $actual = $this->serializer->decode($input, $txType);

        $this->assertSame($expected, $actual);
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
        $refundTests = iterator_to_array(KuveytPosRequestDataMapperTest::createRefundRequestDataProvider());
        yield 'test_refund' => [
            'input'    => $refundTests[0]['expected'],
            'txType'   => PosInterface::TX_TYPE_REFUND,
            'expected' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:DrawBack><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>101</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>DrawBack</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>101</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>101</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:DrawBack></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_partial_refund' => [
            'input'    => $refundTests[1]['expected'],
            'txType'   => PosInterface::TX_TYPE_REFUND_PARTIAL,
            'expected' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:PartialDrawback><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>901</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>PartialDrawback</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>901</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>901</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:PartialDrawback></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_cancel' => [
            'input'    => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'txType'   => PosInterface::TX_TYPE_CANCEL,
            'expected' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_status' => [
            'input'    => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'txType'   => PosInterface::TX_TYPE_STATUS,
            'expected' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_pay' => [
            'input'    => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><abc>1</abc><abc2><abc3>3</abc3></abc2></KuveytTurkVPosMessage>
',
        ];
    }

    public static function decodeHtmlDataProvider(): array
    {
        $htmlWithCustomHtmlElement = <<<HTML
<!DOCTYPE html>
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head runat="server">
        <APM_DO_NOT_TOUCH>

            <script type="text/javascript">
                /** there are scripts here, but I removed them. */
            </script>
        </APM_DO_NOT_TOUCH>

        <script type="text/javascript"
                src="/TSPD/08b0201f60ab2000de2e6870ff4ae1f397120a7871fe6406a168be73c069c11db79ff614055e08fb?type=9"></script>
        <title></title></head>
    <body onload="OnLoadEvent();">
    <form name="downloadForm" action="https://site/gateway/3d/fail?uuid=BR7z5PDu6c" method="POST"><input
            type="hidden" name="AuthenticationResponse"
            value="%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3ePosMerchantIPError%3c%2fResponseCode%3e%3cResponseMessage%3eIP+adresi+tan%c4%b1ml%c4%b1+de%c4%9fildir.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3eEak3mC1eW5%3c%2fMerchantOrderId%3e%3cReferenceId%3ea92e57f52ac443538bdb71b10a6c6fe7%3c%2fReferenceId%3e%3cMerchantId%3e80123%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e">
        <!-- To support javascript unaware/disabled browsers -->
        <noscript>
            <center>Please click the submit button below.<br> <input type="submit" name="submit" value="Submit">
            </center>
        </noscript>
    </form>
    <script language="Javascript">         function OnLoadEvent() {
            document.downloadForm.submit();
        }   </script>
    </body>
    </html>
HTML;

        return [
            [
                'html'     => '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"><head runat="server"><title></title></head><body onload="OnLoadEvent();"><form name="downloadForm" action="https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate" method="POST"><input type="hidden" name="AuthenticationResponse" value="%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E"><noscript><center>Please click the submit button below.<br><input type="submit" name="submit" value="Submit"></center></noscript></form><script language="Javascript">function OnLoadEvent() {document.downloadForm.submit();}</script></body></html>',
                'expected' => [
                    'gateway'     => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                    'form_inputs' => [
                        'AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E',
                    ],
                ],
            ],
            '3d_auth_success_1' => [
                // bazi kredi kartlarda bu sekilde HTML response donuyor
                'html'     => "<!DOCTYPE html SYSTEM 'about:legacy-compat'>\n<html class='no-js' lang='en' xmlns='http://www.w3.org/1999/xhtml'>\n<head>\n<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>\n<meta charset='utf-8'/>\n<title>3D Secure Processing</title>\n</head>\n<body>\n<div id='main'>\n<div id='content'>\n<div id='order'>\n<h2>3D Secure Processing</h2>\n<img src='preloader.gif' alt='Please wait..'/>\n<div id='formdiv'>\n<script type='text/javascript'>\nfunction hideAndSubmitTimed(formid)\n{\nvar timer=setTimeout(function() {hideAndSubmit(formid)},10);\n}\n\nfunction hideAndSubmit(formid)\n{\nvar formx=document.getElementById(formid);\n\tif (formx!=null)\n\t{\n\t\tformx.style.visibility='hidden';\n\t\tformx.submit();\n\t}\n}\n</script>\n<div>\n<form id='threeDSServerWebFlowStartForm' name='threeDSServerWebFlowStartForm' method='POST' action='https://certemvtds.bkm.com.tr/tds/resultFlow'>\n<input type='hidden' name='threeDSServerWebFlowStart' value='eyJhbGciOiJIUzI1NiJ9.ewogICJ0aHJlZURTU2VydmVyV2ViRmxvd1N0YXJ0IiA6IHsKICAgICJhY3F1aXJlcklEIiA6ICIyMDUiLAogICAgInRocmVlRFNTZXJ2ZXJUcmFuc0lEIiA6ICJhN2QyMjQ4Mi1jMjI2LTRkZjUtODkwNC00M2RmOTZmOTJmNDAiLAogICAgInRocmVlRFNSZXF1ZXN0b3JUcmFuc0lEIiA6ICI4ZGVhOGIwYi1mZTg0LTRhZGQtOWI4Mi05MzM2ZWYyMWM1MjciLAogICAgInRpbWVab25lIiA6ICJVVEMrMDM6MDAiLAogICAgInRpbWVTdGFtcCIgOiAiMjAyMjEyMjgxMjU2NDAiLAogICAgInZlcnNpb24iIDogIjEuMC4wIgogIH0KfQ.w7KQvGhrujSZmzyqEBsqJJKb19vJo16pq_PssXcGc6k'/>\n<input type='hidden' name='browserColorDepth' value=''/>\n<input type='hidden' name='browserScreenHeight' value=''/>\n<input type='hidden' name='browserScreenWidth' value=''/>\n<input type='hidden' name='browserTZ' value=''/>\n<input type='hidden' name='browserJavascriptEnabled' value=''/>\n<input type='hidden' name='browserJavaEnabled' value=''/>\n<script type='text/javascript'>\nhideAndSubmitTimed('threeDSServerWebFlowStartForm');\n</script>\n<script type='text/javascript'>\nfunction collectBrowserInformation(formid)\n{\n\tvar form=document.getElementById(formid);\n\tif (form!=null)\n\t{\n\t\tif (form['browserJavascriptEnabled']!=null)\n\t\t{\n\t\t\t// if this script runs js is enabled\n\t\t\tform['browserJavascriptEnabled'].value=\"true\";\n\t\t}\n\t\tif (form['browserJavaEnabled']!=null)\n\t\t{\n\t\t\tform['browserJavaEnabled'].value=navigator.javaEnabled();\n\t\t}\n\t\tif (form['browserColorDepth']!=null)\n\t\t{\n\t\t\tform['browserColorDepth'].value=screen.colorDepth;\n\t\t}\n\t\tif (form['browserScreenHeight']!=null)\n\t\t{\n\t\t\tform['browserScreenHeight'].value=screen.height;\n\t\t}\n\t\tif (form['browserScreenWidth']!=null)\n\t\t{\n\t\t\tform['browserScreenWidth'].value=screen.width;\n\t\t}\n\t\tvar timezoneOffsetField=form['browserTZ'];\n\t\tif (timezoneOffsetField!=null)\n\t\t{\n\t\t\ttimezoneOffsetField.value=new Date().getTimezoneOffset();\n\t\t}\n\t}\n}\ncollectBrowserInformation('threeDSServerWebFlowStartForm');\n</script>\n<noscript>\n<div align='center'>\n<b>Javascript is turned off or not supported!</b>\n<br/>\n</div>\n</noscript>\n<input type='submit' name='submitBtn' value='Please click here to continue'/>\n</form>\n</div>\n</div>\n</div>\n<div id='content-footer'>\n<br/>\n</div>\n</div>\n</div>\n</body>\n</html>\n<script id=\"f5_cspm\">(function(){var f5_cspm={f5_p:'FBLKDLCLIMEKPFGLBIGOMJDHCACMAALBNDHLOOIAPIMPHIGCBKPNEIHONIMDNOPPNBIBNMIJAAMBJDNMLGCAPAMIAAJPHFAIDNEEJAPDJDAAMHLCMIJHLLPPGBDEPCNA',setCharAt:function(str,index,chr){if(index>str.length-1)return str;return str.substr(0,index)+chr+str.substr(index+1);},get_byte:function(str,i){var s=(i/16)|0;i=(i&15);s=s*32;return((str.charCodeAt(i+16+s)-65)<<4)|(str.charCodeAt(i+s)-65);},set_byte:function(str,i,b){var s=(i/16)|0;i=(i&15);s=s*32;str=f5_cspm.setCharAt(str,(i+16+s),String.fromCharCode((b>>4)+65));str=f5_cspm.setCharAt(str,(i+s),String.fromCharCode((b&15)+65));return str;},set_latency:function(str,latency){latency=latency&0xffff;str=f5_cspm.set_byte(str,40,(latency>>8));str=f5_cspm.set_byte(str,41,(latency&0xff));str=f5_cspm.set_byte(str,35,2);return str;},wait_perf_data:function(){try{var wp=window.performance.timing;if(wp.loadEventEnd>0){var res=wp.loadEventEnd-wp.navigationStart;if(res<60001){var cookie_val=f5_cspm.set_latency(f5_cspm.f5_p,res);window.document.cookie='f5avr1306913476aaaaaaaaaaaaaaaa_cspm_='+encodeURIComponent(cookie_val)+';path=/';}\nreturn;}}\ncatch(err){return;}\nsetTimeout(f5_cspm.wait_perf_data,100);return;},go:function(){var chunk=window.document.cookie.split(/\\s*;\\s*/);for(var i=0;i<chunk.length;++i){var pair=chunk[i].split(/\\s*=\\s*/);if(pair[0]=='f5_cspm'&&pair[1]=='1234')\n{var d=new Date();d.setTime(d.getTime()-1000);window.document.cookie='f5_cspm=;expires='+d.toUTCString()+';path=/;';setTimeout(f5_cspm.wait_perf_data,100);}}}}\nf5_cspm.go();}());</script>",
                'expected' => [
                    'gateway'     => 'https://certemvtds.bkm.com.tr/tds/resultFlow',
                    'form_inputs' => [
                        'threeDSServerWebFlowStart' => 'eyJhbGciOiJIUzI1NiJ9.ewogICJ0aHJlZURTU2VydmVyV2ViRmxvd1N0YXJ0IiA6IHsKICAgICJhY3F1aXJlcklEIiA6ICIyMDUiLAogICAgInRocmVlRFNTZXJ2ZXJUcmFuc0lEIiA6ICJhN2QyMjQ4Mi1jMjI2LTRkZjUtODkwNC00M2RmOTZmOTJmNDAiLAogICAgInRocmVlRFNSZXF1ZXN0b3JUcmFuc0lEIiA6ICI4ZGVhOGIwYi1mZTg0LTRhZGQtOWI4Mi05MzM2ZWYyMWM1MjciLAogICAgInRpbWVab25lIiA6ICJVVEMrMDM6MDAiLAogICAgInRpbWVTdGFtcCIgOiAiMjAyMjEyMjgxMjU2NDAiLAogICAgInZlcnNpb24iIDogIjEuMC4wIgogIH0KfQ.w7KQvGhrujSZmzyqEBsqJJKb19vJo16pq_PssXcGc6k',
                        'browserColorDepth'         => '',
                        'browserScreenHeight'       => '',
                        'browserScreenWidth'        => '',
                        'browserTZ'                 => '',
                        'browserJavascriptEnabled'  => '',
                        'browserJavaEnabled'        => '',
                    ],
                ],
            ],
            '3d_auth_fail'      => [
                // fail durum testi
                'html'     => '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"><head runat="server">    <title></title></head><body onload="OnLoadEvent();">    <form name="downloadForm"        action="http://localhost/finansbank-payfor/3d/response.php"        method="POST">         <input type="hidden"  name="AuthenticationResponse" value="%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3efbab348b4c074d1b9a5247471d91f5d1%3c%2fReferenceId%3e%3cMerchantId%3e496%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e">        <!-- To support javascript unaware/disabled browsers -->        <noscript>    <center>Please click the submit button below.<br>    <input type="submit" name="submit" value="Submit"></center>  </noscript>    </form>    <script language="Javascript">         function OnLoadEvent() {document.downloadForm.submit();}   </script></body></html>',
                'expected' => [
                    // 3d form data olusturulmasi icin gonderilen istek banka tarafindan reddedillirse, bankadan fail URL'a yonlendirilecek bir response (html) doner.
                    'gateway'     => 'http://localhost/finansbank-payfor/3d/response.php',
                    'form_inputs' => [
                        'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3efbab348b4c074d1b9a5247471d91f5d1%3c%2fReferenceId%3e%3cMerchantId%3e496%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
                    ],
                ],
            ],
            [
                // test with custom APM_DO_NOT_TOUCH element
                'html'     => $htmlWithCustomHtmlElement,
                'expected' => [
                    'gateway'     => 'https://site/gateway/3d/fail?uuid=BR7z5PDu6c',
                    'form_inputs' => [
                        'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3ePosMerchantIPError%3c%2fResponseCode%3e%3cResponseMessage%3eIP+adresi+tan%c4%b1ml%c4%b1+de%c4%9fildir.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3eEak3mC1eW5%3c%2fMerchantOrderId%3e%3cReferenceId%3ea92e57f52ac443538bdb71b10a6c6fe7%3c%2fReferenceId%3e%3cMerchantId%3e80123%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
                    ],
                ],
            ],
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

    public static function decodeJsonDataProvider(): Generator
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
                        'Value' => [
                            'OrderContract' => [
                                'IsSelected' => 'false',
                                'IsSelectable' => 'true',
                                'OrderId' => '302704156',
                                'MerchantOrderId' => '20260209089B',
                                'MerchantId' => '496',
                                'CardHolderName' => 'John Doe',
                                'CardType' => 'MasterCard',
                                'CardNumber' => '518896******2544',
                                'OrderDate' => '2026-02-09T19:10:09.097',
                                'OrderStatus' => '1',
                                'LastOrderStatus' => '6',
                                'OrderType' => '1',
                                'TransactionStatus' => '1',
                                'FirstAmount' => '10.01',
                                'CancelAmount' => '10.01',
                                'DrawbackAmount' => '0.00',
                                'ClosedAmount' => '0.00',
                                'FEC' => '0949',
                                'VPSEntryMode' => 'ECOM',
                                'InstallmentCount' => '0',
                                'TransactionSecurity' => '3',
                                'ResponseCode' => '00',
                                'ResponseExplain' => 'İşlem gerçekleştirildi.',
                                'EndOfDayStatus' => '2',
                                'TransactionSide' => 'Auto',
                                'CardHolderIPAddress' => '',
                                'MerchantIPAddress' => '207.211.215.148',
                                'MerchantUserName' => 'apitest',
                                'ProvNumber' => '004212',
                                'BatchId' => '623',
                                'CardExpireDate' => '2906',
                                'PosTerminalId' => 'VP008759',
                                'Explain' => '',
                                'Explain2' => '',
                                'Explain3' => '',
                                'RRN' => '604019659177',
                                'Stan' => '659177',
                                'UserName' => 'vposuser2',
                                'HostName' => 'STD8BOATEST1',
                                'SystemDate' => '2026-02-09T19:10:09.087',
                                'UpdateUserName' => 'webgate2',
                                'UpdateHostName' => 'STD8BOATEST1',
                                'UpdateSystemDate' => '2026-02-09T19:10:20.703',
                                'EndOfDayDate' => [
                                    '@xsi:nil' => 'true',
                                    '#' => '',
                                ],

                                'HostIP' => '172.20.8.84',
                                'FECAmount' => '0',
                                'IdentityTaxNumber' => '',
                                'QueryId' => '0',
                                'DebtId' => '0',
                                'DebtorName' => '',
                                'Period' => '',
                                'SurchargeAmount' => '0',
                                'SGKDebtAmount' => '0',
                                'DeferringCount' => [
                                    '@xsi:nil' => 'true',
                                    '#' => '',
                                ],

                            ],

                        ],

                    ],

                ],

            ],
        ];
    }

    public static function decodeExceptionDataProvider(): Generator
    {
        yield 'test1' => [
            'input'                    => '',
            'txType'                   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected_exception_class' => \Exception::class,
        ];
    }
}
