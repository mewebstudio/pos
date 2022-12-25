<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;

/**
 * VakifBankPosRequestDataMapperTest
 */
class VakifBankPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractGateway */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var VakifBankPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

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
        $this->requestDataMapper = new VakifBankPosRequestDataMapper();
        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', VakifBankPosRequestDataMapper::amountFormat(1000));
    }

    /**
     * @return void
     */
    public function testMapRecurringFrequency()
    {
        $this->assertEquals('Month', $this->requestDataMapper->mapRecurringFrequency('MONTH'));
        $this->assertEquals('Month', $this->requestDataMapper->mapRecurringFrequency('Month'));
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
    public function testCreate3DPaymentRequestData()
    {
        $order = $this->order;
        $order['amount'] = 10.1;
        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $txType = AbstractGateway::TX_PAY;
        $gatewayResponse = [
            'Eci'                       => (string) rand(1, 100),
            'Cavv'                      => (string) rand(1, 100),
            'VerifyEnrollmentRequestId' => (string) rand(1, 100),
        ];

        $expectedValue = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $txType, $gatewayResponse, $pos->getCard());
        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $txType, $gatewayResponse, $pos->getCard());
        $this->assertEquals($expectedValue, $actual);

        $order['installment'] = 2;
        $pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $expectedValue = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $txType, $gatewayResponse, $pos->getCard());
        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $txType, $gatewayResponse, $pos->getCard());
        $this->assertEquals($expectedValue, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckData()
    {
        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $expectedValue = $this->getSample3DEnrollmentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedValue, $actual);


        $this->order['installment'] = 2;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $expectedValue = $this->getSample3DEnrollmentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedValue, $actual);

        $this->order['recurringFrequencyType'] = 'DAY';
        $this->order['recurringFrequency'] = 3;
        $this->order['recurringInstallmentCount'] = 2;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $expectedValue = $this->getSample3DEnrollmentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedValue, $actual);
        $this->assertArrayHasKey('RecurringFrequency', $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $pos = $this->pos;
        $order = $this->order;
        $txType = AbstractGateway::TX_PAY;
        $order['amount'] = 1000;
        $pos->prepare($order, $txType, $this->card);

        $expectedValue = $this->getSampleNonSecurePaymentRequestData($pos->getAccount(), $order, $txType, $pos->getCard());
        $actualData = $this->requestDataMapper->createNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), $txType, $pos->getCard());

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $pos = $this->pos;
        $order = $this->order;
        $order['amount'] = 1000;
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $expectedValue = $this->getSampleNonSecurePaymentPostRequestData($pos->getAccount(), $order);
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());

        $this->assertEquals($expectedValue, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $pos = $this->pos;
        $order = $this->order;
        $order['id'] = '15613133';
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $expectedValue = $this->getSampleCancelRequestData($pos->getAccount(), $order);
        $actualData = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $pos = $this->pos;
        $order = $this->order;
        $order['id'] = '15613133';
        $order['amount'] = 1000;
        $this->pos->prepare($order, AbstractGateway::TX_REFUND);

        $expectedValue = $this->getSampleRefundRequestData($pos->getAccount(), $order);
        $actualData = $this->requestDataMapper->createRefundRequestData($pos->getAccount(), $pos->getOrder());

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreate3DFormData()
    {
        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $expectedValue = $this->getSample3DFormDataFromEnrollmentResponse();
        $actualData = $this->requestDataMapper->create3DFormData(
            $pos->getAccount(),
            $pos->getOrder(),
            '',
            '',
            $pos->getCard(),
            $this->getSampleEnrollmentSuccessResponseData()['Message']['VERes']
        );

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return array
     */
    public function getSampleEnrollmentSuccessResponseData(): array
    {
        return [
            'MessageErrorCode' => 'code',
            'ErrorMessage' => 'some error',
            'Message' => [
                'VERes' => [
                    'Status' => 'Y',
                    'PaReq' => 'PaReq2',
                    'TermUrl' => 'TermUrl2',
                    'MD' => 'MD3',
                    'ACSUrl' => 'http',
                ],
            ],
        ];
    }

    public function getSampleEnrollmentFailResponseData(): array
    {
        return [
            'Message'                   => [
                'VERes' => [
                    'Status' => 'E',
                ],
            ],
            'VerifyEnrollmentRequestId' => '0aebb0757acccae6fba75b2e4d78cecf',
            'MessageErrorCode'          => '2005',
            'ErrorMessage'              => 'Merchant cannot be found for this bank',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => 'Cancel',
            'ReferenceTransactionId' => $order['id'],
            'ClientIp'               => $order['ip'],
        ];
    }

    /**
     * @param VakifBankAccount        $account
     * @param                         $order
     * @param string                  $txType
     * @param array                   $responseData
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData, ?AbstractCreditCard $card): array
    {
        $expectedValue = [
            'MerchantId'              => $account->getClientId(),
            'Password'                => $account->getPassword(),
            'TerminalNo'              => $account->getTerminalId(),
            'TransactionType'         => $this->requestDataMapper->mapTxType($txType),
            'OrderId'                 => $order->id,
            'ClientIp'                => $order->ip,
            'CurrencyCode'            => '949',
            'CurrencyAmount'          => $order->amount,
            'OrderDescription'        => '',
            'TransactionId'           => $order->id,
            'Pan'                     => $card->getNumber(),
            'Cvv'                     => $card->getCvv(),
            'CardHoldersName'         => $card->getHolderName(),
            'Expiry'                  => '202112',
            'ECI'                     => $responseData['Eci'],
            'CAVV'                    => $responseData['Cavv'],
            'MpiTransactionId'        => $responseData['VerifyEnrollmentRequestId'],
            'TransactionDeviceSource' => 0,
        ];
        if ($order->installment) {
            $expectedValue['NumberOfInstallments'] = $this->requestDataMapper->mapInstallment($order->installment);
        }

        return $expectedValue;
    }


    /**
     * @param VakifBankAccount        $account
     * @param                         $order
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    private function getSample3DEnrollmentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card): array
    {
        $expectedValue = [
            'MerchantId'                => $account->getClientId(),
            'MerchantPassword'          => $account->getPassword(),
            'MerchantType'              => $account->getMerchantType(),
            'PurchaseAmount'            => $order->amount,
            'VerifyEnrollmentRequestId' => $order->rand,
            'Currency'                  => '949',
            'SuccessUrl'                => $order->success_url,
            'FailureUrl'                => $order->fail_url,
            'SessionInfo'               => $order->extraData,
            'Pan'                       => $card->getNumber(),
            'ExpiryDate'                => '2112',
            'BrandName'                 => '100',
            'IsRecurring'               => 'false',
        ];

        if ($order->installment) {
            $expectedValue['InstallmentCount'] = $this->requestDataMapper->mapInstallment($order->installment);
        }

        if (isset($order->recurringFrequency)) {
            $expectedValue['IsRecurring'] = 'true';
            $expectedValue['RecurringFrequency'] = $order->recurringFrequency;
            $expectedValue['RecurringFrequencyType'] = 'Day';
            $expectedValue['RecurringInstallmentCount'] = $order->recurringInstallmentCount;
            if (isset($order->recurringEndDate)) {
                $expectedValue['RecurringEndDate'] = $order->recurringEndDate;
            }
        }

        return $expectedValue;
    }

    /**
     * @param VakifBankAccount   $account
     * @param                    $order
     * @param string             $txType
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, AbstractCreditCard $card): array
    {
        return [
            'MerchantId'              => $account->getClientId(),
            'Password'                => $account->getPassword(),
            'TerminalNo'              => $account->getTerminalId(),
            'TransactionType'         => $this->requestDataMapper->mapTxType($txType),
            'OrderId'                 => $order['id'],
            'CurrencyAmount'          => '1000.00',
            'CurrencyCode'            => 949,
            'ClientIp'                => $order['ip'],
            'TransactionDeviceSource' => 0,
            'Pan'                     => $card->getNumber(),
            'Expiry'                  => '202112',
            'Cvv'                     => $card->getCvv(),
        ];
    }

    /**
     * @param VakifBankAccount $account
     * @param array            $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TerminalNo'             => $account->getTerminalId(),
            'TransactionType'        => 'Capture',
            'ReferenceTransactionId' => $order['id'],
            'CurrencyAmount'         => '1000.00',
            'CurrencyCode'           => '949',
            'ClientIp'               => $order['ip'],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => 'Refund',
            'ReferenceTransactionId' => $order['id'],
            'ClientIp'               => $order['ip'],
            'CurrencyAmount'         => '1000.00',
        ];
    }

    /**
     * @return array
     */
    private function getSample3DFormDataFromEnrollmentResponse(): array
    {
        $inputs = [
            'PaReq'   => 'PaReq2',
            'TermUrl' => 'TermUrl2',
            'MD'      => 'MD3',
        ];

        return [
            'gateway' => 'http',
            'inputs'  => $inputs,
        ];
    }
}
