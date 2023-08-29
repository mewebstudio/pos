<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\InterPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * InterPosRequestDataMapperTest
 */
class InterPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $account;

    /** @var InterPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var InterPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

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
            $merchantPass
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => AbstractGateway::LANG_TR,
            'rand'        => 'rand',
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config);
        $this->pos->setTestMode(true);

        $crypt = PosFactory::getGatewayCrypt(InterPos::class, new NullLogger());

        $this->requestDataMapper = new InterPosRequestDataMapper($crypt);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency('TRY'));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency('EUR'));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", ""]
     *           ["1", ""]
     *           ["2", "2"]
     *           [2, "2"]
     *
     * @return void
     */
    public function testMapInstallment($installment, $expected)
    {
        $actual = $this->requestDataMapper->mapInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $order = (object) [
            'id'       => '2020110828BC',
            'amount'   => 320,
            'currency' => 'TRY',
        ];

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $order = (object) $this->order;
        $card = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, AbstractGateway::TX_PAY, $card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($order, $card, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = (object) [
            'id'   => '2020110828BC',
            'lang' => AbstractGateway::LANG_EN,
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleCancelXMLData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
    {
        $order        = (object) [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'lang'        => AbstractGateway::LANG_EN,
        ];
        $responseData = [
            'MD'                      => '1',
            'PayerTxnId'              => '2',
            'Eci'                     => '3',
            'PayerAuthenticationCode' => '4',
        ];

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, AbstractGateway::TX_PAY, $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($order, $this->account, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $order   = (object) $this->order;
        $account = $this->account;
        $card   = $this->card;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DModel',
            'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
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
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            AbstractGateway::MODEL_3D_SECURE,
            AbstractGateway::TX_PAY,
            $gatewayURL
        ));

        //test with card
        if ($card) {
            $form['inputs']['CardType'] = '0';
            $form['inputs']['Pan']      = $card->getNumber();
            $form['inputs']['Expiry']   = '1221';
            $form['inputs']['Cvv2']     = $card->getCvv();
        }

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            AbstractGateway::MODEL_3D_SECURE,
            AbstractGateway::TX_PAY,
            $gatewayURL,
            $card
        ));
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $order = (object) $this->order;

        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d_host'];
        $inputs = [
            'ShopCode'         => $this->account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DHost',
            'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
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
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            AbstractGateway::MODEL_3D_HOST,
            AbstractGateway::TX_PAY,
            $gatewayURL
        ));
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = (object) [
            'id'   => '2020110828BC',
            'lang' => AbstractGateway::LANG_EN,
        ];

        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = (object) [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     * @param array           $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData($order, InterPosAccount $account, array $responseData): array
    {
        return [
            'UserCode'                => $account->getUsername(),
            'UserPass'                => $account->getPassword(),
            'ShopCode'                => $account->getClientId(),
            'TxnType'                 => 'Auth',
            'SecureType'              => 'NonSecure',
            'OrderId'                 => $order->id,
            'PurchAmount'             => $order->amount,
            'Currency'                => '949',
            'InstallmentCount'        => '',
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
     * @param                    $order
     * @param AbstractCreditCard $card
     * @param InterPosAccount    $account
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData($order, AbstractCreditCard $card, InterPosAccount $account): array
    {
        $requestData = [
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => 'NonSecure',
            'OrderId'          => $order->id,
            'PurchAmount'      => $order->amount,
            'Currency'         => '949',
            'InstallmentCount' => '',
            'MOTO'             => '0',
            'Lang'             => $order->lang,
        ];

        $requestData['CardType'] = '0';
        $requestData['Pan']      = $card->getNumber();
        $requestData['Expiry']   = '0122';
        $requestData['Cvv2']     = $card->getCvv();

        return $requestData;
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData($order, InterPosAccount $account): array
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
            'Currency'    => '949',
            'MOTO'        => '0',
        ];
    }

    /**
     * @param                 $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusRequestData($order, InterPosAccount $account): array
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
