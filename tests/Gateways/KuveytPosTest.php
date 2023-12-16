<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use Mews\Pos\Factory\SerializerFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapperTest;
use Mews\Pos\Tests\Serializer\KuveytPosSerializerTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\KuveytPos
 */
class KuveytPosTest extends TestCase
{
    private KuveytPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var KuveytPos */
    private PosInterface $pos;

    /**
     * @return void
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createKuveytPosAccount(
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
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, $this->createMock(EventDispatcherInterface::class));

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
        $this->assertEquals($this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'], $this->pos->get3DGatewayURL());
        $this->assertEquals($this->config['banks'][$this->account->getBank()]['gateway_endpoints']['payment_api'], $this->pos->getApiURL());
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
     * @dataProvider get3DFormDataDataProvider
     *
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse(array $sendReturn, array $expected)
    {
        $crypt         = CryptFactory::createGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper = RequestDataMapperFactory::createGatewayRequestMapper(KuveytPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $serializer    = SerializerFactory::createGatewaySerializer(KuveytPos::class);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [
                    'gateway_endpoints' => [
                        'gateway_3d' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                    ],
                ],
                $this->account,
                $requestMapper,
                $this->createMock(KuveytPosResponseDataMapper::class),
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();
        $posMock->setTestMode(true);

        $response = '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><MerchantId>496</MerchantId><CustomerId>400235</CustomerId><UserName>apiuser1</UserName><APIVersion>1.0.0</APIVersion><TransactionType>Sale</TransactionType><TransactionSecurity>3</TransactionSecurity><InstallmentCount>0</InstallmentCount><Amount>1001</Amount><DisplayAmount>1001</DisplayAmount><CurrencyCode>0949</CurrencyCode><MerchantOrderId>2020110828BC</MerchantOrderId><OkUrl>http://localhost/finansbank-payfor/3d/response.php</OkUrl><FailUrl>http://localhost/finansbank-payfor/3d/response.php</FailUrl><CardHolderName>John Doe</CardHolderName><CardType>Visa</CardType><CardNumber>4155650100416111</CardNumber><CardExpireDateYear>25</CardExpireDateYear><CardExpireDateMonth>01</CardExpireDateMonth><CardCVV2>123</CardCVV2><HashData>gqMMbwYulxyJ+8M8qnbGT/21gHU=</HashData></KuveytTurkVPosMessage>
';
        $response = str_replace(["\r"], '', $response);
        $posMock->method('send')
            ->with($response)
            ->willReturn($sendReturn);

        $result = $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);

        $this->assertSame($expected, $result);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthFail()
    {
        $request = Request::create('', 'POST', [
            'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3e9b8e2326a9df44c2b2aac0b98b11f0a4%3c%2fReferenceId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
        ]);

        $this->pos->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
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
        $crypt          = CryptFactory::createGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(KuveytPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(KuveytPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(KuveytPos::class);

        $kuveytPosResponseDataMapperTest = new KuveytPosResponseDataMapperTest();
        $xml                             = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request                         = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $paymentResponse = $kuveytPosResponseDataMapperTest->threeDPaymentDataProvider()['authSuccessPaymentFail2']['paymentData'];
        $posMock->expects($this->once())->method('send')->willReturn($paymentResponse);

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
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
        $crypt                           = CryptFactory::createGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestMapper                   = RequestDataMapperFactory::createGatewayRequestMapper(KuveytPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper                  = ResponseDataMapperFactory::createGatewayResponseMapper(KuveytPos::class, $requestMapper, new NullLogger());
        $serializer                      = SerializerFactory::createGatewaySerializer(KuveytPos::class);
        $kuveytPosResponseDataMapperTest = new KuveytPosResponseDataMapperTest();
        $xml                             = '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><VPosMessage><APIVersion>1.0.0</APIVersion><OkUrl>http://localhost:44785/Home/Success</OkUrl><FailUrl>http://localhost:44785/Home/Fail</FailUrl><HashData>lYJYMi/gVO9MWr32Pshaa/zAbSHY=</HashData><MerchantId>80</MerchantId><SubMerchantId>0</SubMerchantId><CustomerId>400235</CustomerId><UserName>apiuser</UserName><CardNumber>4025502306586032</CardNumber><CardHolderName>afafa</CardHolderName><CardType>MasterCard</CardType><BatchID>0</BatchID><TransactionType>Sale</TransactionType><InstallmentCount>0</InstallmentCount><Amount>100</Amount><DisplayAmount>100</DisplayAmount><MerchantOrderId>Order 123</MerchantOrderId><FECAmount>0</FECAmount><CurrencyCode>0949</CurrencyCode><QeryId>0</QeryId><DebtId>0</DebtId><SurchargeAmount>0</SurchargeAmount><SGKDebtAmount>0</SGKDebtAmount><TransactionSecurity>3</TransactionSecurity><TransactionSide>Auto</TransactionSide><EntryGateMethod>VPOS_ThreeDModelPayGate</EntryGateMethod></VPosMessage><IsEnrolled>true</IsEnrolled><IsVirtual>false</IsVirtual><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><ResponseCode>00</ResponseCode><ResponseMessage>HATATA</ResponseMessage><MD>67YtBfBRTZ0XBKnAHi8c/A==</MD><AuthenticationPacket>WYGDgSIrSHDtYwF/WEN+nfwX63sppA=</AuthenticationPacket><ACSURL>https://acs.bkm.com.tr/mdpayacs/pareq</ACSURL></VPosTransactionResponseContract>';
        $request                         = Request::create('', 'POST', [
            'AuthenticationResponse' => urlencode($xml),
        ]);

        $posMock = $this->getMockBuilder(KuveytPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $paymentResponse = $kuveytPosResponseDataMapperTest->threeDPaymentDataProvider()['success1']['paymentData'];
        $posMock->expects($this->once())->method('send')->willReturn($paymentResponse);

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
        $result = $posMock->getResponse();

        $this->assertIsArray($result);
        $this->assertSame('approved', $result['status']);
        $this->assertSame('00', $result['proc_return_code']);
        $this->assertNotEmpty($result['all']);
        $this->assertNotEmpty($result['3d_all']);
    }

    public function testMakeRegularPayment()
    {
        $this->expectException(NotImplementedException::class);
        $this->pos->makeRegularPayment([], $this->card, PosInterface::TX_PAY);
    }

    public static function get3DFormDataDataProvider(): array
    {
        return [
            [
                'send_return' => KuveytPosSerializerTest::decodeHtmlDataProvider()[0]['expected'],
                'expected'    => [
                    'gateway' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22+encoding%3D%22UTF-8%22%3F%3E%3CVPosTransactionResponseContract%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E4025502306586032%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransactionResponseContract%3E',
                    ],
                ],
            ],
            [
                'send_return' => KuveytPosSerializerTest::decodeHtmlDataProvider()[1]['expected'],
                'expected'    => [
                    'gateway' => 'https://certemvtds.bkm.com.tr/tds/resultFlow',
                    'method'  => 'POST',
                    'inputs'  => [
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
            [
                // fail durum testi
                'send_return' => KuveytPosSerializerTest::decodeHtmlDataProvider()[2]['expected'],
                'expected'    => [
                    // 3d form data olusturulmasi icin gonderilen istek banka tarafindan reddedillirse, bankadan fail URL'a yonlendirilecek bir response (html) doner.
                    'gateway' => 'http://localhost/finansbank-payfor/3d/response.php',
                    'method'  => 'POST',
                    'inputs'  => [
                        'AuthenticationResponse' => '%3c%3fxml+version%3d%221.0%22+encoding%3d%22utf-8%22%3f%3e%3cVPosTransactionResponseContract+xmlns%3axsd%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema%22+xmlns%3axsi%3d%22http%3a%2f%2fwww.w3.org%2f2001%2fXMLSchema-instance%22%3e%3cIsEnrolled%3etrue%3c%2fIsEnrolled%3e%3cIsVirtual%3efalse%3c%2fIsVirtual%3e%3cResponseCode%3eHashDataError%3c%2fResponseCode%3e%3cResponseMessage%3e%c5%9eifrelenen+veriler+(Hashdata)+uyu%c5%9fmamaktad%c4%b1r.%3c%2fResponseMessage%3e%3cOrderId%3e0%3c%2fOrderId%3e%3cTransactionTime%3e0001-01-01T00%3a00%3a00%3c%2fTransactionTime%3e%3cMerchantOrderId%3e2020110828BC%3c%2fMerchantOrderId%3e%3cReferenceId%3efbab348b4c074d1b9a5247471d91f5d1%3c%2fReferenceId%3e%3cMerchantId%3e496%3c%2fMerchantId%3e%3cBusinessKey%3e0%3c%2fBusinessKey%3e%3c%2fVPosTransactionResponseContract%3e',
                    ],
                ],
            ],
        ];
    }
}
