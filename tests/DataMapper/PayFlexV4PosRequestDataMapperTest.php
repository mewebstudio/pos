<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * PayFlexV4PosRequestDataMapperTest
 */
class PayFlexV4PosRequestDataMapperTest extends TestCase
{
    /** @var PayFlexAccount */
    public $account;

    /** @var AbstractCreditCard */
    private $card;

    /** @var PayFlexV4PosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
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

        $pos = PosFactory::createPosGateway($this->account, $config);

        $this->requestDataMapper = new PayFlexV4PosRequestDataMapper();
        $this->card = CreditCardFactory::create($pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', PayFlexV4PosRequestDataMapper::amountFormat(1000));
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
    public function testCreate3DPaymentRequestData()
    {
        $order = $this->order;
        $order['amount'] = 10.1;

        $txType = PosInterface::TX_PAY;
        $gatewayResponse = [
            'Eci'                       => (string) random_int(1, 100),
            'Cavv'                      => (string) random_int(1, 100),
            'VerifyEnrollmentRequestId' => (string) random_int(1, 100),
        ];

        $expectedValue = $this->getSample3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse, $this->card);
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse, $this->card);
        $this->assertEquals($expectedValue, $actual);

        $order['installment'] = 2;
        $expectedValue = $this->getSample3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse, $this->card);
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse, $this->card);
        $this->assertEquals($expectedValue, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckData()
    {
        $expectedValue = $this->getSample3DEnrollmentRequestData($this->account, $this->order, $this->card);
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedValue, $actual);


        $this->order['installment'] = 2;
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, $this->card);
        $expectedValue = $this->getSample3DEnrollmentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedValue, $actual);

        $this->order['recurringFrequencyType'] = 'DAY';
        $this->order['recurringFrequency'] = 3;
        $this->order['recurringInstallmentCount'] = 2;
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, $this->card);
        $expectedValue = $this->getSample3DEnrollmentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedValue, $actual);
        $this->assertArrayHasKey('RecurringFrequency', $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $order = $this->order;
        $txType = PosInterface::TX_PAY;
        $order['amount'] = 1000;

        $expectedValue = $this->getSampleNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);
        $actualData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $order = $this->order;
        $order['amount'] = 1000;

        $expectedValue = $this->getSampleNonSecurePaymentPostRequestData($this->account, $order);
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = $this->order;
        $order['id'] = '15613133';

        $expectedValue = $this->getSampleCancelRequestData($this->account, $order);
        $actualData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = $this->order;
        $order['id'] = '15613133';
        $order['amount'] = 1000;

        $expectedValue = $this->getSampleRefundRequestData($this->account, $order);
        $actualData = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreate3DFormData()
    {
        $expectedValue = $this->getSample3DFormDataFromEnrollmentResponse();
        $actualData = $this->requestDataMapper->create3DFormData(
            null,
            null,
            null,
            null,
            null,
            null,
            $this->getSampleEnrollmentSuccessResponseDataProvider()['Message']['VERes']
        );

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return array
     */
    public static function getSampleEnrollmentSuccessResponseDataProvider(): array
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

    public static function getSampleEnrollmentFailResponseDataProvider(): array
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
     * @param PayFlexAccount          $account
     * @param array                   $order
     * @param string                  $txType
     * @param array                   $responseData
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData, ?AbstractCreditCard $card): array
    {
        $expectedValue = [
            'MerchantId'              => $account->getClientId(),
            'Password'                => $account->getPassword(),
            'TerminalNo'              => $account->getTerminalId(),
            'TransactionType'         => $this->requestDataMapper->mapTxType($txType),
            'OrderId'                 => $order['id'],
            'ClientIp'                => $order['ip'],
            'CurrencyCode'            => '949',
            'CurrencyAmount'          => $order['amount'],
            'OrderDescription'        => '',
            'TransactionId'           => $order['id'],
            'Pan'                     => $card->getNumber(),
            'Cvv'                     => $card->getCvv(),
            'CardHoldersName'         => $card->getHolderName(),
            'Expiry'                  => '202112',
            'ECI'                     => $responseData['Eci'],
            'CAVV'                    => $responseData['Cavv'],
            'MpiTransactionId'        => $responseData['VerifyEnrollmentRequestId'],
            'TransactionDeviceSource' => 0,
        ];
        if ($order['installment']) {
            $expectedValue['NumberOfInstallments'] = $this->requestDataMapper->mapInstallment($order['installment']);
        }

        return $expectedValue;
    }


    /**
     * @param PayFlexAccount          $account
     * @param array                   $order
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    private function getSample3DEnrollmentRequestData(AbstractPosAccount $account, array $order, ?AbstractCreditCard $card): array
    {
        $expectedValue = [
            'MerchantId'                => $account->getClientId(),
            'MerchantPassword'          => $account->getPassword(),
            'MerchantType'              => $account->getMerchantType(),
            'PurchaseAmount'            => $order['amount'],
            'VerifyEnrollmentRequestId' => $order['rand'],
            'Currency'                  => '949',
            'SuccessUrl'                => $order['success_url'],
            'FailureUrl'                => $order['fail_url'],
            'SessionInfo'               => $order['extraData'],
            'Pan'                       => $card->getNumber(),
            'ExpiryDate'                => '2112',
            'BrandName'                 => '100',
            'IsRecurring'               => 'false',
        ];

        if ($order['installment']) {
            $expectedValue['InstallmentCount'] = $this->requestDataMapper->mapInstallment($order['installment']);
        }

        if (isset($order['recurringFrequency'])) {
            $expectedValue['IsRecurring'] = 'true';
            $expectedValue['RecurringFrequency'] = $order['recurringFrequency'];
            $expectedValue['RecurringFrequencyType'] = 'Day';
            $expectedValue['RecurringInstallmentCount'] = $order['recurringInstallmentCount'];
            if (isset($order['recurringEndDate'])) {
                $expectedValue['RecurringEndDate'] = $order['recurringEndDate'];
            }
        }

        return $expectedValue;
    }

    /**
     * @param PayFlexAccount     $account
     * @param array              $order
     * @param string             $txType
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, AbstractCreditCard $card): array
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
     * @param PayFlexAccount   $account
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
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }
}
