<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * KuveytPosTest
 */
class KuveytPosTest extends TestCase
{
    /**
     * @var KuveytPosAccount
     */
    private $threeDAccount;

    private $config;

    /**
     * @var AbstractCreditCard
     */
    private $card;
    private $order;

    /**
     * @var KuveytPos
     */
    private $pos;

    /**
     * @return void
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->threeDAccount = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '496',
            'apiuser1',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'hash'        => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'ip'          => '127.0.0.1',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);

        $this->pos->setTestMode(true);
        $this->card = CreditCardFactory::create(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            AbstractCreditCard::CARD_TYPE_VISA
        );

        $this->xmlDecoder = new XmlEncoder();
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->threeDAccount, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'], $this->pos->get3DGatewayURL());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['urls']['test'], $this->pos->getApiURL());
    }

    /**
     * @return void
     */
    public function testSetTestMode()
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }


    /**
     * @return void
     */
    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    /**
     * @dataProvider parseHTMLResponseTestProvider
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse(string $html, array $expected)
    {
        $crypt = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(KuveytPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(KuveytPos::class, $requestMapper, new NullLogger());
        $posMock     = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [
                    'urls' => [
                        'gateway' => [
                            'test' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                        ],
                    ],
                ],
                $this->threeDAccount,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->method('send')->willReturn($html);

        $result = $posMock->get3DFormData();

        $this->assertSame($expected, $result);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3e9b8e2326a9df44c2b2aac0b98b11f0a4%3c%2fReferenceId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsArray($result);
        $this->assertSame('declined', $result['status']);
        $this->assertSame('Şifrelenen veriler (Hashdata) uyuşmamaktadır.', $result['md_error_message']);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthSuccessProvisionFail()
    {
        $crypt = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(KuveytPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(KuveytPos::class, $requestMapper, new NullLogger());
        $kuveytPosResponseDataMapperTest = new KuveytPosResponseDataMapperTest();
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $xml     = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [],
                $this->threeDAccount,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $paymentResponse = $kuveytPosResponseDataMapperTest->threeDPaymentDataProvider()['authSuccessPaymentFail2']['paymentData'];
        $posMock->expects($this->once())->method('send')->willReturn($paymentResponse);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertSame('declined', $result['status']);
        $this->assertSame('EmptyMDException', $result['proc_return_code']);
        $this->assertNotEmpty($result['all']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthSuccessProvisionSuccess()
    {
        $crypt = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(KuveytPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(KuveytPos::class, $requestMapper, new NullLogger());
        $kuveytPosResponseDataMapperTest = new KuveytPosResponseDataMapperTest();
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $xml     = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [],
                $this->threeDAccount,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $paymentResponse = $kuveytPosResponseDataMapperTest->threeDPaymentDataProvider()['success1']['paymentData'];
        $posMock->expects($this->once())->method('send')->willReturn($paymentResponse);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();

        $this->assertIsArray($result);
        $this->assertSame('approved', $result['status']);
        $this->assertSame('00', $result['proc_return_code']);
        $this->assertNotEmpty($result['all']);
        $this->assertNotEmpty($result['3d_all']);
    }

    public function parseHTMLResponseTestProvider(): array
    {
        return [
            [
                'html' => '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"><head runat="server"><title></title></head><body onload="OnLoadEvent();"><form name="downloadForm" action="https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate" method="POST"><input type="hidden" name="AuthenticationResponse" value="%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E"><noscript><center>Please click the submit button below.<br><input type="submit" name="submit" value="Submit"></center></noscript></form><script language="Javascript">function OnLoadEvent() {document.downloadForm.submit();}</script></body></html>',
                'expected' => [
                    'gateway' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                    'inputs' => [
                        'AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E'
                    ]
                ],
            ],
            [
                // bazi kredi kartlarda bu sekilde HTML response donuyor
                'html' => "<!DOCTYPE html SYSTEM 'about:legacy-compat'>\n<html class='no-js' lang='en' xmlns='http://www.w3.org/1999/xhtml'>\n<head>\n<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>\n<meta charset='utf-8'/>\n<title>3D Secure Processing</title>\n</head>\n<body>\n<div id='main'>\n<div id='content'>\n<div id='order'>\n<h2>3D Secure Processing</h2>\n<img src='preloader.gif' alt='Please wait..'/>\n<div id='formdiv'>\n<script type='text/javascript'>\nfunction hideAndSubmitTimed(formid)\n{\nvar timer=setTimeout(function() {hideAndSubmit(formid)},10);\n}\n\nfunction hideAndSubmit(formid)\n{\nvar formx=document.getElementById(formid);\n\tif (formx!=null)\n\t{\n\t\tformx.style.visibility='hidden';\n\t\tformx.submit();\n\t}\n}\n</script>\n<div>\n<form id='threeDSServerWebFlowStartForm' name='threeDSServerWebFlowStartForm' method='POST' action='https://certemvtds.bkm.com.tr/tds/resultFlow'>\n<input type='hidden' name='threeDSServerWebFlowStart' value='eyJhbGciOiJIUzI1NiJ9.ewogICJ0aHJlZURTU2VydmVyV2ViRmxvd1N0YXJ0IiA6IHsKICAgICJhY3F1aXJlcklEIiA6ICIyMDUiLAogICAgInRocmVlRFNTZXJ2ZXJUcmFuc0lEIiA6ICJhN2QyMjQ4Mi1jMjI2LTRkZjUtODkwNC00M2RmOTZmOTJmNDAiLAogICAgInRocmVlRFNSZXF1ZXN0b3JUcmFuc0lEIiA6ICI4ZGVhOGIwYi1mZTg0LTRhZGQtOWI4Mi05MzM2ZWYyMWM1MjciLAogICAgInRpbWVab25lIiA6ICJVVEMrMDM6MDAiLAogICAgInRpbWVTdGFtcCIgOiAiMjAyMjEyMjgxMjU2NDAiLAogICAgInZlcnNpb24iIDogIjEuMC4wIgogIH0KfQ.w7KQvGhrujSZmzyqEBsqJJKb19vJo16pq_PssXcGc6k'/>\n<input type='hidden' name='browserColorDepth' value=''/>\n<input type='hidden' name='browserScreenHeight' value=''/>\n<input type='hidden' name='browserScreenWidth' value=''/>\n<input type='hidden' name='browserTZ' value=''/>\n<input type='hidden' name='browserJavascriptEnabled' value=''/>\n<input type='hidden' name='browserJavaEnabled' value=''/>\n<script type='text/javascript'>\nhideAndSubmitTimed('threeDSServerWebFlowStartForm');\n</script>\n<script type='text/javascript'>\nfunction collectBrowserInformation(formid)\n{\n\tvar form=document.getElementById(formid);\n\tif (form!=null)\n\t{\n\t\tif (form['browserJavascriptEnabled']!=null)\n\t\t{\n\t\t\t// if this script runs js is enabled\n\t\t\tform['browserJavascriptEnabled'].value=\"true\";\n\t\t}\n\t\tif (form['browserJavaEnabled']!=null)\n\t\t{\n\t\t\tform['browserJavaEnabled'].value=navigator.javaEnabled();\n\t\t}\n\t\tif (form['browserColorDepth']!=null)\n\t\t{\n\t\t\tform['browserColorDepth'].value=screen.colorDepth;\n\t\t}\n\t\tif (form['browserScreenHeight']!=null)\n\t\t{\n\t\t\tform['browserScreenHeight'].value=screen.height;\n\t\t}\n\t\tif (form['browserScreenWidth']!=null)\n\t\t{\n\t\t\tform['browserScreenWidth'].value=screen.width;\n\t\t}\n\t\tvar timezoneOffsetField=form['browserTZ'];\n\t\tif (timezoneOffsetField!=null)\n\t\t{\n\t\t\ttimezoneOffsetField.value=new Date().getTimezoneOffset();\n\t\t}\n\t}\n}\ncollectBrowserInformation('threeDSServerWebFlowStartForm');\n</script>\n<noscript>\n<div align='center'>\n<b>Javascript is turned off or not supported!</b>\n<br/>\n</div>\n</noscript>\n<input type='submit' name='submitBtn' value='Please click here to continue'/>\n</form>\n</div>\n</div>\n</div>\n<div id='content-footer'>\n<br/>\n</div>\n</div>\n</div>\n</body>\n</html>\n<script id=\"f5_cspm\">(function(){var f5_cspm={f5_p:'FBLKDLCLIMEKPFGLBIGOMJDHCACMAALBNDHLOOIAPIMPHIGCBKPNEIHONIMDNOPPNBIBNMIJAAMBJDNMLGCAPAMIAAJPHFAIDNEEJAPDJDAAMHLCMIJHLLPPGBDEPCNA',setCharAt:function(str,index,chr){if(index>str.length-1)return str;return str.substr(0,index)+chr+str.substr(index+1);},get_byte:function(str,i){var s=(i/16)|0;i=(i&15);s=s*32;return((str.charCodeAt(i+16+s)-65)<<4)|(str.charCodeAt(i+s)-65);},set_byte:function(str,i,b){var s=(i/16)|0;i=(i&15);s=s*32;str=f5_cspm.setCharAt(str,(i+16+s),String.fromCharCode((b>>4)+65));str=f5_cspm.setCharAt(str,(i+s),String.fromCharCode((b&15)+65));return str;},set_latency:function(str,latency){latency=latency&0xffff;str=f5_cspm.set_byte(str,40,(latency>>8));str=f5_cspm.set_byte(str,41,(latency&0xff));str=f5_cspm.set_byte(str,35,2);return str;},wait_perf_data:function(){try{var wp=window.performance.timing;if(wp.loadEventEnd>0){var res=wp.loadEventEnd-wp.navigationStart;if(res<60001){var cookie_val=f5_cspm.set_latency(f5_cspm.f5_p,res);window.document.cookie='f5avr1306913476aaaaaaaaaaaaaaaa_cspm_='+encodeURIComponent(cookie_val)+';path=/';}\nreturn;}}\ncatch(err){return;}\nsetTimeout(f5_cspm.wait_perf_data,100);return;},go:function(){var chunk=window.document.cookie.split(/\\s*;\\s*/);for(var i=0;i<chunk.length;++i){var pair=chunk[i].split(/\\s*=\\s*/);if(pair[0]=='f5_cspm'&&pair[1]=='1234')\n{var d=new Date();d.setTime(d.getTime()-1000);window.document.cookie='f5_cspm=;expires='+d.toUTCString()+';path=/;';setTimeout(f5_cspm.wait_perf_data,100);}}}}\nf5_cspm.go();}());</script>",
                'expected' => [
                    'gateway' => 'https://certemvtds.bkm.com.tr/tds/resultFlow',
                    'inputs' => [
                        'threeDSServerWebFlowStart' => 'eyJhbGciOiJIUzI1NiJ9.ewogICJ0aHJlZURTU2VydmVyV2ViRmxvd1N0YXJ0IiA6IHsKICAgICJhY3F1aXJlcklEIiA6ICIyMDUiLAogICAgInRocmVlRFNTZXJ2ZXJUcmFuc0lEIiA6ICJhN2QyMjQ4Mi1jMjI2LTRkZjUtODkwNC00M2RmOTZmOTJmNDAiLAogICAgInRocmVlRFNSZXF1ZXN0b3JUcmFuc0lEIiA6ICI4ZGVhOGIwYi1mZTg0LTRhZGQtOWI4Mi05MzM2ZWYyMWM1MjciLAogICAgInRpbWVab25lIiA6ICJVVEMrMDM6MDAiLAogICAgInRpbWVTdGFtcCIgOiAiMjAyMjEyMjgxMjU2NDAiLAogICAgInZlcnNpb24iIDogIjEuMC4wIgogIH0KfQ.w7KQvGhrujSZmzyqEBsqJJKb19vJo16pq_PssXcGc6k',
                        'browserColorDepth' => '',
                        'browserScreenHeight' => '',
                        'browserScreenWidth' => '',
                        'browserTZ' => '',
                        'browserJavascriptEnabled' => '',
                        'browserJavaEnabled' => '',
                    ]
                ],
            ],
            [
                // fail durum testi
                'html' => '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"><head runat="server">    <title></title></head><body onload="OnLoadEvent();">    <form name="downloadForm"        action="http://localhost/finansbank-payfor/3d/response.php"        method="POST">         <input type="hidden"  name="AuthenticationResponse" value="%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3efbab348b4c074d1b9a5247471d91f5d1%3c%2fReferenceId%3e%3cMerchantId%3e496%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e">        <!-- To support javascript unaware/disabled browsers -->        <noscript>    <center>Please click the submit button below.<br>    <input type="submit" name="submit" value="Submit"></center>  </noscript>    </form>    <script language="Javascript">         function OnLoadEvent() {document.downloadForm.submit();}   </script></body></html>',
                'expected' => [
                    // 3d form data olusturulmasi icin gonderilen istek banka tarafindan reddedillirse, bankadan fail URL'a yonlendirilecek bir response (html) doner.
                    'gateway' => 'http://localhost/finansbank-payfor/3d/response.php',
                    'inputs' => [
                        'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3efbab348b4c074d1b9a5247471d91f5d1%3c%2fReferenceId%3e%3cMerchantId%3e496%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
                    ]
                ],
            ],
        ];
    }
}
