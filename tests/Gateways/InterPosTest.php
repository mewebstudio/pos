<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterPos;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\InterPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

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

        $this->account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            '3d',
            $merchantPass,
            \Mews\Pos\Gateways\InterPos::LANG_TR
        );

        $this->card = new CreditCardInterPos('5555444433332222', '21', '12', '122', 'ahmet', 'visa');

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => \Mews\Pos\Gateways\InterPos::LANG_TR,
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
        $account = AccountFactory::createInterPosAccount('denizbank', 'XXXXXXX', 'XXXXXXX', 'XXXXXXX', '3d_host', 'VnM5WZ3sGrPusmWP', InterPos::LANG_TR);
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


        $card = new CreditCardInterPos('5555444433332222', '22', '01', '123', 'ahmet', 'visa');
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
     * @param                    $order
     * @param CreditCardInterPos $card
     * @param InterPosAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, $card, $account)
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

        if ($card) {
            $requestData['CardType'] = $card->getCardCode();
            $requestData['Pan']      = $card->getNumber();
            $requestData['Expiry']   = $card->getExpirationDate();
            $requestData['Cvv2']     = $card->getCvv();
        }

        return $requestData;
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
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
    private function getSample3DPaymentXMLData($order, $account, array $responseData)
    {
        $requestData = [
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

        return $requestData;
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
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
    private function getSampleCancelXMLData($order, $account)
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
    private function getSampleRefundXMLData($order, $account)
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


    /**
     * @param string $name
     *
     * @return ReflectionMethod
     *
     * @throws ReflectionException
     */
    private static function getProtectedMethod(string $name)
    {
        $class  = new ReflectionClass(InterPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
