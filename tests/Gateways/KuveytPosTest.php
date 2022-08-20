<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\KuveytPos;
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
     * @return void
     */
    public function testGetCommon3DFormDataFailedResponse()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->pos->setTestMode(false);

        $result = $this->pos->get3DFormData();
        $this->assertArrayHasKey('AuthenticationResponse', $result['inputs']);
        //form data olusturulmasi icin gonderilen istek banka tarafindan reddedillirse, banka failURL'a yonlendirilecek bir response doner.
        //istek basarili olursa, gateway = bankanin gateway URL'ne esit olur.
        $this->assertSame($result['gateway'], $this->order['fail_url']);
    }

    /**
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse()
    {

        $testGateway = 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate';
        $posMock     = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([['urls' => [
                'gateway' => [
                    'test' => $testGateway,
                ],
            ], ], $this->threeDAccount, PosFactory::getGatewayMapper(KuveytPos::class), new NullLogger()])
            ->onlyMethods(['send'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->method('send')->willReturn('<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"><head runat="server"><title></title></head><body onload="OnLoadEvent();"><form name="downloadForm" action="https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate" method="POST"><input type="hidden" name="AuthenticationResponse" value="%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E"><noscript><center>Please click the submit button below.<br><input type="submit" name="submit" value="Submit"></center></noscript></form><script language="Javascript">function OnLoadEvent() {document.downloadForm.submit();}</script></body></html>');

        $result = $posMock->get3DFormData();
        $this->assertArrayHasKey('AuthenticationResponse', $result['inputs']);
        //form data olusturulmasi icin gonderilen istek banka tarafindan reddedillirse, banka failURL'a yonlendirilecek bir response doner.
        //istek basarili olursa, gateway = bankanin gateway URL'ne esit olur.
        $this->assertSame($testGateway, $result['gateway']);
    }

    public function testMap3DPaymentData3DAuthSuccessPaymentFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);

        $threeDAuthSuccessResponse = [
            '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            'VPosMessage' => [
                'OrderId' => '86483278',
                'OkUrl' => 'https://www.example.com/testodeme',
                'FailUrl' => 'https://www.example.com/testodeme',
                'MerchantId' => '48544',
                'SubMerchantId' => '0',
                'CustomerId' => '123456',
                'UserName' => 'fapapi',
                'HashPassword' => 'Hiorgg24rNeRdHUvMCg//mOJn4U=',
                'CardNumber' => '5124********1609',
                'BatchID' => '1576',
                'InstallmentCount' => '0',
                'Amount' => '10',
                'CancelAmount' => '0',
                'MerchantOrderId' => 'MP-15',
                'FECAmount' => '0',
                'CurrencyCode' => '949',
                'QeryId' => '0',
                'DebtId' => '0',
                'SurchargeAmount' => '0',
                'SGKDebtAmount' => '0',
                'TransactionSecurity' => '3',
                'DeferringCount' => [
                    '@xsi:nil' => 'true',
                    '#' => '',
                ],
                'InstallmentMaturityCommisionFlag' => '0',
                'PaymentId' => [
                    '@xsi:nil' => 'true',
                    '#' => '',
                ],
                'OrderPOSTransactionId' => [
                    '@xsi:nil' => 'true',
                    '#' => '',
                ],
                'TranDate' => [
                    '@xsi:nil' => 'true',
                    '#' => '',
                ],
                'TransactionUserId' => [
                    '@xsi:nil' => 'true',
                    '#' => '',
                ],
            ],
            'IsEnrolled' => 'true',
            'IsVirtual' => 'false',
            'ResponseCode' => '00',
            'ResponseMessage' => 'Kart doğrulandı.',
            'OrderId' => '86483278',
            'TransactionTime' => '0001-01-01T00:00:00',
            'MerchantOrderId' => 'MP-15',
            'HashData' => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
            'MD' => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
            'BusinessKey' => '202208456498416947',
        ];

        $paymentFailResponse = [
            '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            'IsEnrolled' => 'true',
            'IsVirtual' => 'false',
            'ResponseCode' => 'MetaDataNotFound',
            'ResponseMessage' => 'Ödeme detayı bulunamadı.',
            'OrderId' => '0',
            'TransactionTime' => '0001-01-01T00:00:00',
            'BusinessKey' => '0',
        ];

        $map3DPaymentData = self::getMethod('map3DPaymentData');
        $result = $map3DPaymentData->invoke($this->pos, $threeDAuthSuccessResponse, $paymentFailResponse);
        unset($result['3d_all']);
        unset($result['all']);

        $expected = [
            'transaction_security' => 'MPI fallback',
            'md_status' => null,
            'hash' => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
            'rand' => null,
            'hash_params' => null,
            'hash_params_val' => null,
            'amount' => '10',
            'currency' => 'TRY',
            'tx_status' => null,
            'md_error_message' => null,
            'masked_number' => '5124********1609',
            'id' => null,
            'trans_id' => null,
            'auth_code' => null,
            'host_ref_num' => null,
            'error_message' => 'Ödeme detayı bulunamadı.',
            'order_id' => 'MP-15',
            'response' => 'Declined',
            'transaction_type' => 'pay',
            'transaction' => 'Sale',
            'proc_return_code' => 'MetaDataNotFound',
            'code' => 'MetaDataNotFound',
            'status' => 'declined',
            'status_detail' => 'MetaDataNotFound',
            'error_code' => 'MetaDataNotFound',
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * @return void
     *
     * @throws GuzzleException
     */
    public function testMake3DPaymentAuthFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3e9b8e2326a9df44c2b2aac0b98b11f0a4%3c%2fReferenceId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame('Şifrelenen veriler (Hashdata) uyuşmamaktadır.', $result['md_error_message']);
    }

    /**
     * @return void
     *
     * @throws GuzzleException
     */
    public function testMake3DPaymentAuthSuccessProvisionFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $xml     = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([[], $this->threeDAccount, PosFactory::getGatewayMapper(KuveytPos::class), new NullLogger()])
            ->onlyMethods(['send', 'check3DHash'])
            ->getMock();

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->expects($this->once())->method('send')->willReturn([
            'IsEnrolled'      => 'false',
            'IsVirtual'       => 'false',
            'ResponseCode'    => 'EmptyMDException',
            'ResponseMessage' => 'Geçerli bir MD değeri giriniz.',
            'OrderId'         => '0',
            'TransactionTime' => '0001-01-01T00:00:00',
            'BusinessKey'     => '0',
        ]);
        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $result = (array) $result;

        $this->assertSame('declined', $result['status']);
        $this->assertSame('EmptyMDException', $result['proc_return_code']);
        $this->assertSame('EmptyMDException', $result['error_code']);
        $this->assertSame('Geçerli bir MD değeri giriniz.', $result['error_message']);
        $this->assertSame('Order 123', $result['order_id']);
        $this->assertSame('Sale', $result['transaction']);
        $this->assertSame('4025502306586032', $result['masked_number']);
        $this->assertSame('100', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('lYJYMi/gVO9MWr32Pshaa/zAbSHY=', $result['hash']);
        $this->assertNotEmpty($result['all']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     *
     * @throws GuzzleException
     */
    public function testMake3DPaymentAuthSuccessProvisionSuccess()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $xml     = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([[], $this->threeDAccount, PosFactory::getGatewayMapper(KuveytPos::class), new NullLogger()])
            ->onlyMethods(['send', 'check3DHash'])
            ->getMock();

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('send')->willReturn([
            'VPosMessage'     => [
                'OrderId'             => '4480',
                'OkUrl'               => 'http://localhost:10398//ThreeDModel/SuccessXml',
                'FailUrl'             => 'http://localhost:10398//ThreeDModel/FailXml',
                'MerchantId'          => '80',
                'SubMerchantId'       => '0',
                'CustomerId'          => '400235',
                'HashPassword'        => 'c77dFssAnYSy6O2MJo+5tMYtGVc=',
                'CardNumber'          => '4025502306586032',
                'BatchID'             => '1906',
                'InstallmentCount'    => '0',
                'Amount'              => '100',
                'MerchantOrderId'     => '660723214',
                'FECAmount'           => '0',
                'CurrencyCode'        => '949',
                'QeryId'              => '0',
                'DebtId'              => '0',
                'SurchargeAmount'     => '0',
                'SGKDebtAmount'       => '0',
                'TransactionSecurity' => '0',
            ],
            'IsEnrolled'      => 'true',
            'ProvisionNumber' => '896626',
            'RRN'             => '904115005554',
            'Stan'            => '005554',
            'ResponseCode'    => '00',
            'ResponseMessage' => 'OTORİZASYON VERİLDİ',
            'OrderId'         => '4480',
            'TransactionTime' => '0001-01-01T00:00:00',
            'MerchantOrderId' => '660723214',
            'HashData'        => 'I7H/6nwfydM6VcwXsl82mqeC83o=',
        ]);

        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $result = (array) $result;

        $this->assertSame('approved', $result['status']);
        $this->assertSame('00', $result['proc_return_code']);
        $this->assertNull($result['error_code']);
        $this->assertSame('660723214', $result['order_id']);
        $this->assertSame('Sale', $result['transaction']);
        $this->assertSame('4025502306586032', $result['masked_number']);
        $this->assertSame('100', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('lYJYMi/gVO9MWr32Pshaa/zAbSHY=', $result['hash']);
        $this->assertNotEmpty($result['all']);
        $this->assertNotEmpty($result['3d_all']);
    }


    protected static function getMethod(string $name)
    {
        $class = new \ReflectionClass(KuveytPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
