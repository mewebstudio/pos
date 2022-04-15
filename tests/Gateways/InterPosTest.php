<?php

namespace Mews\Pos\Tests\Gateways;

use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardInterPos;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\InterPos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\InterPos
 */
class InterPosTest extends TestCase
{
    /**
     * @var InterPosAccount
     */
    private $account;
    /**
     * @var InterPos
     */
    private $pos;
    private $config;

    /**
     * @var CreditCardInterPos
     */
    private $card;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            AbstractGateway::MODEL_3D_SECURE,
            $merchantPass,
            InterPos::LANG_TR
        );

        $this->card = new CreditCardInterPos('5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => InterPos::LANG_TR,
            'rand'        => microtime(true),
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testGet3DFormWithCardData()
    {
        $order   = (object) $this->order;
        $account = $this->account;
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $hash = $this->pos->create3DHash($account, $this->pos->getOrder());


        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DModel',
            'Hash'             => $hash,
            'PurchAmount'      => $order->amount,
            'OrderId'          => $order->id,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Lang'             => $order->lang,
            'Currency'         => '949',
            'InstallmentCount' => '',
        ];
        $card   = $this->card;
        if ($card) {
            $inputs['CardType'] = $card->getCardCode();
            $inputs['Pan']      = $card->getNumber();
            $inputs['Expiry']   = $card->getExpirationDate();
            $inputs['Cvv2']     = $card->getCvv();
        }

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DFormWithoutCardData()
    {
        $order   = (object) $this->order;
        $account = $this->account;
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $hash = $this->pos->create3DHash($account, $this->pos->getOrder());

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DModel',
            'Hash'             => $hash,
            'PurchAmount'      => $order->amount,
            'OrderId'          => $order->id,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Lang'             => $order->lang,
            'Currency'         => '949',
            'InstallmentCount' => '',
        ];

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DHostFormData()
    {
        $account = AccountFactory::createInterPosAccount(
            'denizbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_HOST,
            'VnM5WZ3sGrPusmWP',
            InterPos::LANG_TR
        );
        /** @var InterPos $pos */
        $pos     = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        $pos->prepare($this->order, AbstractGateway::TX_PAY);

        $order = (object) $this->order;
        $hash  = $pos->create3DHash($account, $pos->getOrder());

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DHost',
            'Hash'             => $hash,
            'PurchAmount'      => $order->amount,
            'OrderId'          => $order->id,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Lang'             => $order->lang,
            'Currency'         => '949',
            'InstallmentCount' => '',
        ];

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway_3d_host']['test'],
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $pos->get3DFormData());
    }

    public function testCheck3DHash()
    {
        $data = [
            'Version'        => '',
            'PurchAmount'    => 320,
            'Exponent'       => '',
            'Currency'       => '949',
            'OkUrl'          => 'https://localhost/pos/examples/interpos/3d/success.php',
            'FailUrl'        => 'https://localhost/pos/examples/interpos/3d/fail.php',
            'MD'             => '',
            'OrderId'        => '20220327140D',
            'ProcReturnCode' => '81',
            'Response'       => '',
            'mdStatus'       => '0',
            'HASH'           => '9DZVckklZFjuoA7sl4MN0l7VDMo=',
            'HASHPARAMS'     => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL'  => '320949https://localhost/pos/examples/interpos/3d/success.phphttps://localhost/pos/examples/interpos/3d/fail.php20220327140D810',
        ];

        $this->assertTrue($this->pos->check3DHash($this->account, $data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($this->account, $data));
    }

    public function testCreateRegularPaymentXML()
    {
        $order = $this->order;

        $card = new CreditCardInterPos('5555444433332222', '22', '01', '123', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actual = $pos->createRegularPaymentXML();

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actual);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 320,
            'currency' => 'TRY',
        ];

        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actual = $pos->createRegularPostXML();

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actual);
    }

    public function testCreate3DPaymentXML()
    {
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'lang'        => InterPos::LANG_EN,
        ];
        $responseData = [
            'MD'                      => '',
            'PayerTxnId'              => '',
            'Eci'                     => '',
            'PayerAuthenticationCode' => '',
        ];

        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $pos->create3DPaymentXML($responseData);

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => InterPos::LANG_EN,
        ];

        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actual = $pos->createStatusXML();

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actual);
    }


    public function testCreateCancelXML()
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => InterPos::LANG_EN,
        ];

        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actual = $pos->createCancelXML();

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actual);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        /** @var InterPos $pos */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actual = $pos->createRefundXML();

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actual);
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
            'Version' => '',
            'MerchantID' => '',
            'ShopCode' => '3123',
            'TxnStat' => 'N',
            'MD' => '',
            'RetCode' => '',
            'RetDet' => '',
            'VenderCode' => '',
            'Eci' => '',
            'PayerAuthenticationCode' => '',
            'PayerTxnId' => '',
            'CavvAlg' => '',
            'PAResVerified' => 'False',
            'PAResSyntaxOK' => 'False',
            'Expiry' => '****',
            'Pan' => '409070******0057',
            'OrderId' => '202204155912',
            'PurchAmount' => '30',
            'Exponent' => '',
            'Description' => '',
            'Description2' => '',
            'Currency' => '949',
            'OkUrl' => 'http://localhost/interpos/3d/response.php',
            'FailUrl' => 'http://localhost/interpos/3d/response.php',
            '3DStatus' => '0',
            'AuthCode' => '',
            'HostRefNum' => 'hostid',
            'TransId' => '',
            'TRXDATE' => '',
            'CardHolderName' => '',
            'mdStatus' => '0',
            'ProcReturnCode' => '81',
            'TxnResult' => '',
            'ErrorMessage' => 'Terminal Aktif Degil',
            'ErrorCode' => 'B810002',
            'Response' => '',
            'HASH' => '4hSLIFy/RNlEdB7sUYNnP7kAqzM=',
            'HASHPARAMS' => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL' => '30949http://localhost/interpos/3d/response.phphttp://localhost/interpos/3d/response.php202204155912810',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertSame('B810002', $result['error_code']);
        $this->assertSame('202204155912', $result['order_id']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame('409070******0057', $result['masked_number']);
        $this->assertSame('30', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('4hSLIFy/RNlEdB7sUYNnP7kAqzM=', $result['hash']);
        $this->assertSame('Terminal Aktif Degil', $result['error_message']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertNull($result['all']);
    }

    /**
     * @return void
     *
     * @throws GuzzleException
     */
    public function testMake3DPayPaymentFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $request = Request::create('', 'POST', [
            'Version' => '',
            'MerchantID' => '',
            'ShopCode' => '3123',
            'TxnStat' => 'N',
            'MD' => '',
            'RetCode' => '',
            'RetDet' => '',
            'VenderCode' => '',
            'Eci' => '',
            'PayerAuthenticationCode' => '',
            'PayerTxnId' => '',
            'CavvAlg' => '',
            'PAResVerified' => 'False',
            'PAResSyntaxOK' => 'False',
            'Expiry' => '****',
            'Pan' => '409070******0057',
            'OrderId' => '202204155912',
            'PurchAmount' => '30',
            'Exponent' => '',
            'Description' => '',
            'Description2' => '',
            'Currency' => '949',
            'OkUrl' => 'http://localhost/interpos/3d-pay/response.php',
            'FailUrl' => 'http://localhost/interpos/3d-pay/response.php',
            '3DStatus' => '0',
            'AuthCode' => '',
            'HostRefNum' => 'hostid',
            'TransId' => '',
            'TRXDATE' => '',
            'CardHolderName' => '',
            'mdStatus' => '0',
            'ProcReturnCode' => '81',
            'TxnResult' => '',
            'ErrorMessage' => 'Terminal Aktif Degil',
            'ErrorCode' => 'B810002',
            'Response' => '',
            'HASH' => 'klXFUEWTgMc6pRZJFsQRMTOa9us=',
            'HASHPARAMS' => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL' => '30949http://localhost/interpos/3d-pay/response.phphttp://localhost/interpos/3d-pay/response.php20220415D7F8810',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertSame('B810002', $result['error_code']);
        $this->assertSame('202204155912', $result['order_id']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame('409070******0057', $result['masked_number']);
        $this->assertSame('30', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('klXFUEWTgMc6pRZJFsQRMTOa9us=', $result['hash']);
        $this->assertSame('Terminal Aktif Degil', $result['error_message']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertNull($result['all']);
    }

    /**
     * @param                    $order
     * @param CreditCardInterPos $card
     * @param InterPosAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, CreditCardInterPos $card, InterPosAccount $account): array
    {
        $requestData = [
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => 'NonSecure',
            'OrderId'          => $order->id,
            'PurchAmount'      => $order->amount,
            'Currency'         => $order->currency,
            'InstallmentCount' => $order->installment,
            'MOTO'             => '0',
            'Lang'             => $order->lang,
        ];

        $requestData['CardType'] = $card->getCardCode();
        $requestData['Pan']      = $card->getNumber();
        $requestData['Expiry']   = $card->getExpirationDate();
        $requestData['Cvv2']     = $card->getCvv();

        return $requestData;
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, InterPosAccount $account): array
    {
        return [
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'ShopCode'    => $account->getClientId(),
            'TxnType'     => 'PostAuth',
            'SecureType'  => 'NonSecure',
            'OrderId'     => null,
            'orgOrderId'  => $order->id,
            'PurchAmount' => $order->amount,
            'Currency'    => $order->currency,
            'MOTO'        => '0',
        ];
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     * @param array           $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($order, InterPosAccount $account, array $responseData): array
    {
        return [
            'UserCode'                => $account->getUsername(),
            'UserPass'                => $account->getPassword(),
            'ClientId'                => $account->getClientId(),
            'TxnType'                 => 'Auth',
            'SecureType'              => 'NonSecure',
            'OrderId'                 => $order->id,
            'PurchAmount'             => $order->amount,
            'Currency'                => $order->currency,
            'InstallmentCount'        => $order->installment,
            'MD'                      => $responseData['MD'],
            'PayerTxnId'              => $responseData['PayerTxnId'],
            'Eci'                     => $responseData['Eci'],
            'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
            'MOTO'                    => '0',
            'Lang'                    => $order->lang,
        ];
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, InterPosAccount $account): array
    {
        return [
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'ShopCode'   => $account->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order->id,
            'TxnType'    => 'StatusHistory',
            'SecureType' => 'NonSecure',
            'Lang'       => $order->lang,
        ];
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, InterPosAccount $account): array
    {
        return [
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'ShopCode'   => $account->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order->id,
            'TxnType'    => 'Void',
            'SecureType' => 'NonSecure',
            'Lang'       => $order->lang,
        ];
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, InterPosAccount $account): array
    {
        return [
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'ShopCode'    => $account->getClientId(),
            'OrderId'     => null,
            'orgOrderId'  => $order->id,
            'PurchAmount' => $order->amount,
            'TxnType'     => 'Refund',
            'SecureType'  => 'NonSecure',
            'Lang'        => $account->getLang(),
            'MOTO'        => '0',
        ];
    }
}
