<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\CreditCardVakifBank;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankPos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class VakifBankPostTest extends TestCase
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
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount('vakifbank', '000000000111111', '3XTgER89as', 'VP999999', '3d');

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
            'rand'        => microtime(),
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
            'InstallmentCount'          => $order->installment,
            'Pan'                       => $this->card->getNumber(),
            'ExpiryDate'                => $this->card->getExpirationDate(),
            'BrandName'                 => $this->card->getCardCode(),
            'IsRecurring'               => 'false',
        ];

        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());
    }

    public function testRecurringCreate3DEnrollmentCheckData()
    {
        $order = $this->order;
        $order['recurringFrequencyType'] = 'Day';
        $order['recurringFrequency'] = 3;
        $order['recurringInstallmentCount'] = 2;

        $this->pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
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
            'InstallmentCount'          => $order->installment,
            'Pan'                       => $this->card->getNumber(),
            'ExpiryDate'                => $this->card->getExpirationDate(),
            'BrandName'                 => $this->card->getCardCode(),
            'IsRecurring'               => 'true',
            'RecurringFrequency'        => $order->recurringFrequency,
            'RecurringFrequencyType'    => $order->recurringFrequencyType,
            'RecurringInstallmentCount' => $order->recurringInstallmentCount,
        ];

        $this->assertEquals($expectedValue, $this->pos->create3DEnrollmentCheckData());
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
            'Expiry'                  => $this->card->getExpirationDate(),
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
}
