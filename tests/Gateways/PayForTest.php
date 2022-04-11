<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardPayFor;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PayForPos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class PayForTest extends TestCase
{
    /**
     * @var PayForAccount
     */
    private $threeDAccount;

    private $config;

    /**
     * @var CreditCardPayFor
     */
    private $card;
    private $order;

    /**
     * @var PayForPos
     */
    private $pos;

    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;

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

        $this->card = new CreditCardPayFor('5555444433332222', '22', '01', '123', 'ahmet');

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
            'hash'        => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'lang'        => PayForPos::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);

        $this->pos->setTestMode(true);

        $this->xmlDecoder = new XmlEncoder();
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->threeDAccount, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'], $this->pos->get3DGatewayURL());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['urls']['test'], $this->pos->getApiURL());

    }

    public function testSetTestMode()
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }


    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testGet3DFormDataWithCard()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $order = $this->pos->getOrder();
        $form = [
            'gateway' => $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'MbrId'            => PayForPos::MBR_ID,
                'MerchantID'       => $this->threeDAccount->getClientId(),
                'UserCode'         => $this->threeDAccount->getUsername(),
                'OrderId'          => $order->id,
                'Lang'             => $order->lang,
                'SecureType'       => '3DModel',
                'TxnType'          => 'Auth',
                'PurchAmount'      => $order->amount,
                'InstallmentCount' => $order->installment,
                'Currency'         => $order->currency,
                'OkUrl'            => $order->success_url,
                'FailUrl'          => $order->fail_url,
                'Rnd'              => $order->rand,
                'Hash'             => $this->pos->create3DHash(),
                'CardHolderName'   => 'ahmet',
                'Pan'              => '5555444433332222',
                'Expiry'           => '0122',
                'Cvv2'             => '123',
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DFormDataWithoutCard()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $order = $this->pos->getOrder();
        $form = [
            'gateway' => $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'MbrId'            => PayForPos::MBR_ID,
                'MerchantID'       => $this->threeDAccount->getClientId(),
                'UserCode'         => $this->threeDAccount->getUsername(),
                'OrderId'          => $order->id,
                'Lang'             => $order->lang,
                'SecureType'       => '3DModel',
                'TxnType'          => 'Auth',
                'PurchAmount'      => $order->amount,
                'InstallmentCount' => $order->installment,
                'Currency'         => $order->currency,
                'OkUrl'            => $order->success_url,
                'FailUrl'          => $order->fail_url,
                'Rnd'              => $order->rand,
                'Hash'             => $this->pos->create3DHash(),
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testCreate3DHash()
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
        ];
        $hash = 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=';
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $this->assertEquals($hash, $this->pos->create3DHash());
    }

    public function testCheck3DHash()
    {
        $data = [
            "OrderId"        => '2020110828BC',
            "AuthCode"       => "",
            "3DStatus"       => "1",
            "ProcReturnCode" => "V033",
            "ResponseRnd"    => "PF637404392360825218",
            "ResponseHash"   => "ogupUOYY6vQ4+opqDqgLk3DLK7I=",
        ];

        $this->assertTrue($this->pos->check3DHash($data));

        $data['3DStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
    }

    public function testCreateRegularPaymentXML()
    {

        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'lang'        => PayForPos::LANG_TR,
        ];

        $card = new CreditCardPayFor('5555444433332222', '22', '01', '123', 'ahmet');
        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actualXML = $pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'lang'        => PayForPos::LANG_TR,
        ];

        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actualXML = $pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreate3DPaymentXML()
    {

        $order = [
            'id' => '2020110828BC',
        ];
        $responseData = ['RequestGuid' => '1000000057437884'];

        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualXML = $pos->createStatusXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateCancelXML()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
        ];

        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actualXML = $pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
            'amount'   => 10.1,
        ];

        /**
         * @var PayForPos $pos
         */
        $pos = PosFactory::createPosGateway($this->threeDAccount);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actualXML = $pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @param                    $order
     * @param AbstractCreditCard $card
     * @param PayForAccount      $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, $card, $account)
    {
        return [
            'MbrId'            => PayForPos::MBR_ID,
            'MerchantId'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'MOTO'             => PayForPos::MOTO,
            'OrderId'          => $order->id,
            'SecureType'       => 'NonSecure',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order->amount,
            'Currency'         => $order->currency,
            'InstallmentCount' => $order->installment,
            'Lang'             => 'tr',
            'CardHolderName'   => $card->getHolderName(),
            'Pan'              => $card->getNumber(),
            'Expiry'           => $card->getExpirationDate(),
            'Cvv2'             => $card->getCvv(),
        ];
    }

    /**
     * @param               $order
     * @param PayForAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
    {
        return [
            'MbrId'       => PayForPos::MBR_ID,
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order->id,
            'SecureType'  => 'NonSecure',
            'TxnType'     => 'PostAuth',
            'PurchAmount' => $order->amount,
            'Currency'    => $order->currency,
            'Lang'        => 'tr',
        ];
    }

    /**
     * @param               $order
     * @param PayForAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($order, $account, array $responseData)
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
     * @param               $order
     * @param PayForAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
    {
        return [
            'MbrId'      => PayForPos::MBR_ID,
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
     * @param               $order
     * @param PayForAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, $account)
    {
        return [
            'MbrId'      => PayForPos::MBR_ID,
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order->id,
            'SecureType' => 'NonSecure',
            'Lang'       => 'tr',
            'TxnType'    => 'Void',
            'Currency'   => $order->currency,
        ];
    }

    /**
     * @param               $order
     * @param PayForAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, $account)
    {
        return [
            'MbrId'       => PayForPos::MBR_ID,
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order->id,
            'SecureType'  => 'NonSecure',
            'Lang'        => 'tr',
            'TxnType'     => 'Refund',
            'PurchAmount' => $order->amount,
            'Currency'    => $order->currency,
        ];
    }
}
