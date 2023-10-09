<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * InterPosRequestDataMapperTest
 */
class InterPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $account;

    /** @var AbstractCreditCard */
    private $card;

    /** @var InterPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
            'rand'        => 'rand',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $crypt = CryptFactory::createGatewayCrypt(InterPos::class, new NullLogger());

        $this->requestDataMapper = new InterPosRequestDataMapper($dispatcher, $crypt);

        $this->card = CreditCardFactory::create($pos, '5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
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
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 320,
            'currency' => PosInterface::CURRENCY_TRY,
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
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_PAY, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->order, $this->card, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => PosInterface::LANG_EN,
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
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'lang'        => PosInterface::LANG_EN,
        ];
        $responseData = [
            'MD'                      => '1',
            'PayerTxnId'              => '2',
            'Eci'                     => '3',
            'PayerAuthenticationCode' => '4',
        ];

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, PosInterface::TX_PAY, $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($order, $this->account, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $account = $this->account;
        $card   = $this->card;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DModel',
            'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
            'PurchAmount'      => $this->order['amount'],
            'OrderId'          => $this->order['id'],
            'OkUrl'            => $this->order['success_url'],
            'FailUrl'          => $this->order['fail_url'],
            'Rnd'              => $this->order['rand'],
            'Lang'             => $this->order['lang'],
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
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_PAY,
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
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_PAY,
            $gatewayURL,
            $card
        ));
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d_host'];
        $inputs = [
            'ShopCode'         => $this->account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => '3DHost',
            'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
            'PurchAmount'      => $this->order['amount'],
            'OrderId'          => $this->order['id'],
            'OkUrl'            => $this->order['success_url'],
            'FailUrl'          => $this->order['fail_url'],
            'Rnd'              => $this->order['rand'],
            'Lang'             => $this->order['lang'],
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
            $this->order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_PAY,
            $gatewayURL
        ));
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => PosInterface::LANG_EN,
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
        $order = [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param array           $order
     * @param InterPosAccount $account
     * @param array           $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(array $order, InterPosAccount $account, array $responseData): array
    {
        return [
            'UserCode'                => $account->getUsername(),
            'UserPass'                => $account->getPassword(),
            'ShopCode'                => $account->getClientId(),
            'TxnType'                 => 'Auth',
            'SecureType'              => 'NonSecure',
            'OrderId'                 => $order['id'],
            'PurchAmount'             => $order['amount'],
            'Currency'                => '949',
            'InstallmentCount'        => '',
            'MD'                      => $responseData['MD'],
            'PayerTxnId'              => $responseData['PayerTxnId'],
            'Eci'                     => $responseData['Eci'],
            'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
            'MOTO'                    => '0',
            'Lang'                    => $order['lang'],
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData(array $order, InterPosAccount $account): array
    {
        return [
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'ShopCode'   => $account->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order['id'],
            'TxnType'    => 'Void',
            'SecureType' => 'NonSecure',
            'Lang'       => $order['lang'],
        ];
    }

    /**
     * @param array              $order
     * @param AbstractCreditCard $card
     * @param InterPosAccount    $account
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(array $order, AbstractCreditCard $card, InterPosAccount $account): array
    {
        $requestData = [
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => 'NonSecure',
            'OrderId'          => $order['id'],
            'PurchAmount'      => $order['amount'],
            'Currency'         => '949',
            'InstallmentCount' => '',
            'MOTO'             => '0',
            'Lang'             => $order['lang'],
        ];

        $requestData['CardType'] = '0';
        $requestData['Pan']      = $card->getNumber();
        $requestData['Expiry']   = '1221';
        $requestData['Cvv2']     = $card->getCvv();

        return $requestData;
    }

    /**
     * @param array           $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(array $order, InterPosAccount $account): array
    {
        return [
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'ShopCode'    => $account->getClientId(),
            'TxnType'     => 'PostAuth',
            'SecureType'  => 'NonSecure',
            'OrderId'     => null,
            'orgOrderId'  => $order['id'],
            'PurchAmount' => $order['amount'],
            'Currency'    => '949',
            'MOTO'        => '0',
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusRequestData(array $order, InterPosAccount $account): array
    {
        return [
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'ShopCode'   => $account->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order['id'],
            'TxnType'    => 'StatusHistory',
            'SecureType' => 'NonSecure',
            'Lang'       => $order['lang'],
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData(array $order, InterPosAccount $account): array
    {
        return [
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'ShopCode'    => $account->getClientId(),
            'OrderId'     => null,
            'orgOrderId'  => $order['id'],
            'PurchAmount' => $order['amount'],
            'TxnType'     => 'Refund',
            'SecureType'  => 'NonSecure',
            'Lang'        => $account->getLang(),
            'MOTO'        => '0',
        ];
    }
}
