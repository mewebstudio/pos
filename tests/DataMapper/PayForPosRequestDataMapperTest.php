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
    private $account;

    /** @var AbstractCreditCard */
    private $card;

    /** @var PayForPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPayForAccount(
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

        $pos = PosFactory::createPosGateway($this->account, $this->config);

        $crypt = PosFactory::getGatewayCrypt(PayForPos::class, new NullLogger());
        $this->requestDataMapper = new PayForPosRequestDataMapper($crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
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
     * @testWith ["0", "0"]
     *           ["1", "0"]
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
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, (object) $order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($this->account, (object) $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, (object) $this->order, AbstractGateway::TX_PAY, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->account, (object) $this->order, $this->card);
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

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, (object) $order);

        $expectedData = $this->getSampleCancelXMLData($this->account, (object) $order);
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

        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, (object) [], $order);

        $expectedData = $this->getSampleHistoryRequestData($this->account, $order);
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

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, (object) $order, '', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($this->account, (object) $order, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $order   = (object) $this->order;
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];
        $inputs = [
            'MbrId'            => '5',
            'MerchantID'       => $this->account->getClientId(),
            'UserCode'         => $this->account->getUsername(),
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
            $form['inputs']['CardHolderName'] = $card->getHolderName();
            $form['inputs']['Pan']            = $card->getNumber();
            $form['inputs']['Expiry']         = '0122';
            $form['inputs']['Cvv2']           = $card->getCvv();
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
        $order      = (object) $this->order;

        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d_host'];
        $inputs     = [
            'MbrId'            => '5',
            'MerchantID'       => $this->account->getClientId(),
            'UserCode'         => $this->account->getUsername(),
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
            'id' => '2020110828BC',
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = (object) [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
            'amount'   => 10.1,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($this->account, $order);
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
