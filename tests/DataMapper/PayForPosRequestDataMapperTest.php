<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PayForPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * PayForPosRequestDataMapperTest
 */
class PayForPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var PayForPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var PayForPosRequestDataMapper */
    private $requestDataMapper;

    private $order;
    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->threeDAccount = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'email'       => 'mail@customer.com', // optional
            'name'        => 'John Doe', // optional
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);
        $this->pos->setTestMode(true);
        $crypt = PosFactory::getGatewayCrypt(PayForPos::class, new NullLogger());
        $this->requestDataMapper = new PayForPosRequestDataMapper($crypt);
        $this->card              = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet');
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
     * @testWith ["0", 0]
     *           ["1", 0]
     *           ["2", 2]
     *           [2, 2]
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
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $order = $this->order;
        $pos   = $this->pos;
        $card  = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), AbstractGateway::TX_PAY, $card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
        ];
        $pos   = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actual = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleCancelXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateHistoryRequestData()
    {
        $order = [
            'orderId' => '2020110828BC',
            'reqDate' => '20220518',
        ];
        $pos   = $this->pos;

        $actual = $this->requestDataMapper->createHistoryRequestData($pos->getAccount(), (object) [], $order);

        $expectedData = $this->getSampleHistoryRequestData($pos->getAccount(), $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
    {
        $order        = [
            'id' => '2020110828BC',
        ];
        $responseData = ['RequestGuid' => '1000000057437884'];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), '', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $order   = (object) $this->order;
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'];
        $inputs = [
            'MbrId'            => '5',
            'MerchantID'       => $this->threeDAccount->getClientId(),
            'UserCode'         => $this->threeDAccount->getUsername(),
            'OrderId'          => $order->id,
            'Lang'             => $order->lang,
            'SecureType'       => '3DModel',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order->amount,
            'InstallmentCount' => 0,
            'Currency'         => 949,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Hash'             => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
        ];
        $form   = [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            AbstractGateway::TX_PAY,
            $gatewayURL
        ));

        //test with card
        if ($card) {
            $form['inputs']['CardHolderName'] = $card->getHolderName();
            $form['inputs']['Pan']            = $card->getNumber();
            $form['inputs']['Expiry']         = '0122';
            $form['inputs']['Cvv2']           = $card->getCvv();
        }

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
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
        $account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            AbstractGateway::MODEL_3D_HOST,
            '12345678'
        );
        /** @var PayForPos $pos */
        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $order      = $pos->getOrder();
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway_3d_host']['test'];
        $inputs     = [
            'MbrId'            => '5',
            'MerchantID'       => $this->threeDAccount->getClientId(),
            'UserCode'         => $this->threeDAccount->getUsername(),
            'OrderId'          => $order->id,
            'Lang'             => 'tr',
            'SecureType'       => '3DHost',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order->amount,
            'InstallmentCount' => 0,
            'Currency'         => 949,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Hash'             => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
        ];
        $form       = [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $pos->getAccount(),
            $pos->getOrder(),
            AbstractGateway::TX_PAY,
            $gatewayURL
        ));
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualData = $this->requestDataMapper->createStatusRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleStatusRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
            'amount'   => 10.1,
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actual = $this->requestDataMapper->createRefundRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleRefundXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param array              $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, $order, array $responseData): array
    {
        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrderId'     => $order->id,
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order->id,
            'SecureType' => 'NonSecure',
            'Lang'       => 'tr',
            'TxnType'    => 'Void',
            'Currency'   => 949,
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, $order, AbstractCreditCard $card): array
    {
        return [
            'MbrId'            => '5',
            'MerchantId'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'MOTO'             => '0',
            'OrderId'          => $order->id,
            'SecureType'       => 'NonSecure',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order->amount,
            'Currency'         => 949,
            'InstallmentCount' => 0,
            'Lang'             => 'tr',
            'CardHolderName'   => $card->getHolderName(),
            'Pan'              => $card->getNumber(),
            'Expiry'           => '0122',
            'Cvv2'             => $card->getCvv(),
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order->id,
            'SecureType'  => 'NonSecure',
            'TxnType'     => 'PostAuth',
            'PurchAmount' => $order->amount,
            'Currency'    => 949,
            'Lang'        => 'tr',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order->id,
            'SecureType' => 'Inquiry',
            'Lang'       => 'tr',
            'TxnType'    => 'OrderInquiry',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order->id,
            'SecureType'  => 'NonSecure',
            'Lang'        => 'tr',
            'TxnType'     => 'Refund',
            'PurchAmount' => $order->amount,
            'Currency'    => 949,
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $customQueryData
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, $customQueryData): array
    {
        $requestData = [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'SecureType' => 'Report',
            'TxnType'    => 'TxnHistory',
            'Lang'       => 'tr',
        ];

        if (isset($customQueryData['orderId'])) {
            $requestData['OrderId'] = $customQueryData['orderId'];
        } elseif (isset($customQueryData['reqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $customQueryData['reqDate'];
        }

        return $requestData;
    }
}
