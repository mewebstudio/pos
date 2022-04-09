<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\CreditCardVakifBank;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class VakifBankPosTest extends TestCase
{
    /**
     * @var VakifBankAccount
     */
    private $account;
    /**
     * @var VakifBankPos
     */
    private $pos;
    private $config;

    /**
     * @var CreditCardVakifBank
     */
    private $card;

    /** @var array */
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );

        $this->card = new CreditCardVakifBank('5555444433332222', '2021', '12', '122', 'ahmet', 'visa');

        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'extraData'   => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);

        $this->xmlDecoder = new XmlEncoder();
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', VakifBankPos::amountFormat(1000));
    }

    public function testPrepare()
    {

        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testMapRecurringFrequency()
    {
        $this->assertEquals('Month', $this->pos->mapRecurringFrequency('MONTH'));
        $this->assertEquals('Month', $this->pos->mapRecurringFrequency('Month'));
    }

    public function testCreate3DEnrollmentCheckData()
    {

        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $order = $this->pos->getOrder();
        $expectedValue = [
            'MerchantId'                => $this->account->getClientId(),
            'MerchantPassword'          => $this->account->getPassword(),
            'MerchantType'              => $this->account->getMerchantType(),
            'PurchaseAmount'            => $order->amount,
            'VerifyEnrollmentRequestId' => $order->rand,
            'Currency'                  => $order->currency,
            'SuccessUrl'                => $order->success_url,
            'FailureUrl'                => $order->fail_url,
            'SessionInfo'               => $order->extraData,
            'Pan'                       => $this->card->getNumber(),
            'ExpiryDate'                => $this->card->getExpirationDate(),
            'BrandName'                 => $this->card->getCardCode(),
            'IsRecurring'               => 'false',
        ];

        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());


        $this->order['installment'] = 2;
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $order = $this->pos->getOrder();

        $expectedValue['InstallmentCount'] = $order->installment;
        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());
    }

    public function testRecurringCreate3DEnrollmentCheckData()
    {
        $order = $this->order;
        $order['recurringFrequencyType'] = 'Day';
        $order['recurringFrequency'] = 3;
        $order['recurringInstallmentCount'] = 2;

        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $posOrder = $this->pos->getOrder();

        $expectedValue = [
            'MerchantId'                => $this->account->getClientId(),
            'MerchantPassword'          => $this->account->getPassword(),
            'MerchantType'              => $this->account->getMerchantType(),
            'PurchaseAmount'            => $posOrder->amount,
            'VerifyEnrollmentRequestId' => $posOrder->rand,
            'Currency'                  => $posOrder->currency,
            'SuccessUrl'                => $posOrder->success_url,
            'FailureUrl'                => $posOrder->fail_url,
            'Pan'                       => $this->card->getNumber(),
            'ExpiryDate'                => $this->card->getExpirationDate(),
            'BrandName'                 => $this->card->getCardCode(),
            'IsRecurring'               => 'true',
            'RecurringFrequency'        => $posOrder->recurringFrequency,
            'RecurringFrequencyType'    => $posOrder->recurringFrequencyType,
            'RecurringInstallmentCount' => $posOrder->recurringInstallmentCount,
            'SessionInfo'               => $posOrder->extraData,
        ];

        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());


        $order['installment'] = 2;
        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $posOrder = $this->pos->getOrder();

        $expectedValue['InstallmentCount'] = $posOrder->installment;
        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());
    }

    public function testCreate3DPaymentXML()
    {
        $order = $this->order;
        $order['amount'] = 1000;
        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $preparedOrder = $this->pos->getOrder();
        $gatewayResponse = [
            'Eci'                       => (string) rand(1, 100),
            'Cavv'                      => (string) rand(1, 100),
            'VerifyEnrollmentRequestId' => (string) rand(1, 100),
        ];
        $expectedValue = [
            'MerchantId'              => $this->account->getClientId(),
            'Password'                => $this->account->getPassword(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => 'Sale',
            'OrderId'                 => $preparedOrder->id,
            'ClientIp'                => $preparedOrder->ip,
            'CurrencyCode'            => $preparedOrder->currency,
            'CurrencyAmount'          => $preparedOrder->amount,
            'OrderDescription'        => '',
            'TransactionId'           => $preparedOrder->id,
            'Pan'                     => $this->card->getNumber(),
            'Cvv'                     => $this->card->getCvv(),
            'CardHoldersName'         => $this->card->getHolderName(),
            'Expiry'                  => $this->card->getExpirationDateLong(),
            'ECI'                     => $gatewayResponse['Eci'],
            'CAVV'                    => $gatewayResponse['Cavv'],
            'MpiTransactionId'        => $gatewayResponse['VerifyEnrollmentRequestId'],
            'TransactionDeviceSource' => 0,
        ];

        $actualXML = $this->pos->create3DPaymentXML($gatewayResponse);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);


        $order['installment'] = 2;
        $expectedValue['NumberOfInstallments'] = $order['installment'];
        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);

        $actualXML = $this->pos->create3DPaymentXML($gatewayResponse);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);
    }

    public function testCreateRegularPaymentXML()
    {
        $order = $this->order;
        $order['amount'] = 1000;
        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);

        $expectedValue = [
            'MerchantId'              => $this->account->getClientId(),
            'Password'                => $this->account->getPassword(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => 'Sale',
            'OrderId'                 => $order['id'],
            'CurrencyAmount'          => '1000.00',
            'CurrencyCode'            => 949,
            'ClientIp'                => $order['ip'],
            'TransactionDeviceSource' => 0,
            'Pan'                     => $this->card->getNumber(),
            'Expiry'                  => $this->card->getExpirationDateLong(),
            'Cvv'                     => $this->card->getCvv(),
        ];

        $actualXML = $this->pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);
    }

    public function testCreateRegularPostXML()
    {
        $order = $this->order;
        $order['amount'] = 1000;
        $this->pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $expectedValue = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TerminalNo'             => $this->account->getTerminalId(),
            'TransactionType'        => 'Capture',
            'ReferenceTransactionId' => $order['id'],
            'CurrencyAmount'         => '1000.00',
            'CurrencyCode'           => '949',
            'ClientIp'               => $order['ip'],
        ];

        $actualXML = $this->pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);
    }

    public function testCreateCancelXML()
    {
        $order = $this->order;
        $order['id'] = '15613133';
        $this->pos->prepare($order, AbstractGateway::TX_CANCEL);

        $expectedValue = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TransactionType'        => 'Cancel',
            'ReferenceTransactionId' => $order['id'],
            'ClientIp'               => $order['ip'],
        ];

        $actualXML = $this->pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);
    }

    public function testCreateRefundXML()
    {
        $order = $this->order;
        $order['id'] = '15613133';
        $order['amount'] = 1000;
        $this->pos->prepare($order, AbstractGateway::TX_REFUND);

        $expectedValue = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TransactionType'        => 'Refund',
            'ReferenceTransactionId' => $order['id'],
            'ClientIp'               => $order['ip'],
            'CurrencyAmount'         => '1000.00',
        ];

        $actualXML = $this->pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @covers \Mews\Pos\Gateways\VakifBankPos::map3DPaymentData
     *
     * @return void
     */
    public function testMap3DPaymentData3DSuccess()
    {
        $card  = $this->card;
        $order = $this->order;
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $preparedOrder  = (array) $this->pos->getOrder();
        $threeDResponse = [
            'MerchantId'                => $this->account->getClientId(),
            'SubMerchantNo'             => $this->account->getSubMerchantId(),
            'SubMerchantName'           => null,
            'SubMerchantNumber'         => null,
            'PurchAmount'               => $preparedOrder['amount'] * 100,
            'PurchCurrency'             => $preparedOrder['currency'],
            'VerifyEnrollmentRequestId' => $preparedOrder['id'],
            'SessionInfo'               => $preparedOrder['extraData'],
            'InstallmentCount'          => null,
            'Pan'                       => $card->getNumber(),
            'Expiry'                    => $card->getExpirationDate(),
            'Xid'                       => md5(uniqid(rand(), true)),
            'Status'                    => 'Y',
            'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
            'Eci'                       => '02',
            'ExpSign'                   => null,
            'ErrorCode'                 => null,
            'ErrorMessage'              => null,
        ];

        $provisionResponse = [
            'MerchantId'              => $this->account->getClientId(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => 'Sale',
            'TransactionId'           => $preparedOrder['extraData'], //todo why it is equal to extraData?
            'ResultCode'              => '0000',
            'ResultDetail'            => 'İŞLEM BAŞARILI',
            'CustomItems'             => (object) [],
            'InstallmentTable'        => null,
            'CampaignResult'          => null,
            'AuthCode'                => '822641',
            'HostDate'                => '20220404123456',
            'Rrn'                     => '209411062014',
            'CurrencyAmount'          => $preparedOrder['amount'],
            'CurrencyCode'            => $preparedOrder['currency'],
            'OrderId'                 => $preparedOrder['id'],
            'TLAmount'                => $preparedOrder['amount'],
            'ECI'                     => '02',
            'ThreeDSecureType'        => '2',
            'TransactionDeviceSource' => '0',
            'BatchNo'                 => '1',
        ];

        $method  = $this->getMethod('map3DPaymentData');
        $result = $method->invoke($this->pos, $threeDResponse, (object) $provisionResponse);

        $expected = [
            'eci'              => $provisionResponse['ECI'],
            'cavv'             => $threeDResponse['Cavv'],
            'auth_code'        => $provisionResponse['AuthCode'],
            'order_id'         => $provisionResponse['OrderId'],
            'status'           => 'approved',
            'status_detail'    => $provisionResponse['ResultDetail'],
            'error_code'       => null,
            'error_message'    => null,
            'all'              => (object) $provisionResponse,
            '3d_all'           => $threeDResponse,
            'id'               => $provisionResponse['AuthCode'],
            'trans_id'         => $provisionResponse['TransactionId'],
            'host_ref_num'     => $provisionResponse['Rrn'],
            'transaction'      => $provisionResponse['TransactionType'],
            'transaction_type' => $provisionResponse['TransactionType'],
            'response'         => null,
            'proc_return_code' => $provisionResponse['ResultCode'],
            'code'             => $provisionResponse['ResultCode'],
        ];

        $this->assertEquals($expected, (array) $result);
    }

    /**
     * @covers \Mews\Pos\Gateways\VakifBankPos::map3DPaymentData
     *
     * @return void
     */
    public function testMap3DPaymentData3DFail()
    {
        $card  = $this->card;
        $order = $this->order;
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $preparedOrder  = (array) $this->pos->getOrder();
        $threeDResponse = [
            'MerchantId'                => $this->account->getClientId(),
            'SubMerchantNo'             => $this->account->getSubMerchantId(),
            'SubMerchantName'           => null,
            'SubMerchantNumber'         => null,
            'PurchAmount'               => $preparedOrder['amount'] * 100,
            'PurchCurrency'             => $preparedOrder['currency'],
            'VerifyEnrollmentRequestId' => $preparedOrder['id'],
            'SessionInfo'               => $preparedOrder['extraData'],
            'InstallmentCount'          => null,
            'Pan'                       => $card->getNumber(),
            'Expiry'                    => $card->getExpirationDate(),
            'Xid'                       => md5(uniqid(rand(), true)),
            'Status'                    => 'E', //diger hata durumlari N, U
            'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
            'Eci'                       => '02',
            'ExpSign'                   => '',
            'ErrorCode'                 => '1105',
            'ErrorMessage'              => 'Üye isyeri IP si sistemde tanimli degil',
        ];

        $provisionResponse = [];

        $method  = $this->getMethod('map3DPaymentData');
        $result = $method->invoke($this->pos, $threeDResponse, (object) $provisionResponse);

        $expected = [
            'eci'              => $threeDResponse['Eci'],
            'cavv'             => $threeDResponse['Cavv'],
            'auth_code'        => null,
            'order_id'         => $threeDResponse['VerifyEnrollmentRequestId'],
            'status'           => 'declined',
            'status_detail'    => null,
            'error_code'       => $threeDResponse['ErrorCode'],
            'error_message'    => $threeDResponse['ErrorMessage'],
            'all'              => (object) $provisionResponse,
            '3d_all'           => $threeDResponse,
            'id'               => null,
            'trans_id'         => null,
            'host_ref_num'     => null,
            'transaction_type' => 'Sale',
            'transaction'      => 'Sale',
            'proc_return_code' => null,
            'code'             => null,
        ];

        $this->assertEquals($expected, (array) $result);
    }

    private static function getMethod(string $name): \ReflectionMethod
    {
        $class = new ReflectionClass(VakifBankPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
