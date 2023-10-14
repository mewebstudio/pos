<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

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

        $config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->requestDataMapper = new PayFlexV4PosRequestDataMapper(
            $dispatcher,
            $this->createMock(CryptInterface::class)
        );
        $this->card = CreditCardFactory::create($pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', $this->requestDataMapper->amountFormat(1000));
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
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
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
     * @dataProvider three3DEnrollmentRequestDataDataProvider
     */
    public function testCreate3DEnrollmentCheckData(array $order, ?AbstractCreditCard $card, array $expected)
    {
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $card);
        $this->assertEquals($expected, $actual);
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

    public static function three3DEnrollmentRequestDataDataProvider(): \Generator
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => 'rand123',
            'ip'          => '127.0.0.1',
        ];

        $card = new CreditCard('5555444433332222', new \DateTimeImmutable('2021-12-01'), '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

        yield [
            'order'    => $order,
            'card'     => $card,
            'expected' => [
                'MerchantId'                => '000000000111111',
                'MerchantPassword'          => '3XTgER89as',
                'MerchantType'              => 0,
                'PurchaseAmount'            => '100.00',
                'VerifyEnrollmentRequestId' => 'rand123',
                'Currency'                  => '949',
                'SuccessUrl'                => 'https://domain.com/success',
                'FailureUrl'                => 'https://domain.com/fail_url',
                'Pan'                       => '5555444433332222',
                'ExpiryDate'                => '2112',
                'BrandName'                 => '100',
                'IsRecurring'               => 'false',
            ],
        ];

        $order['installment'] = 2;
        yield 'with_installment' => [
            'order'    => $order,
            'card'     => $card,
            'expected' => [
                'MerchantId'                => '000000000111111',
                'MerchantPassword'          => '3XTgER89as',
                'MerchantType'              => 0,
                'PurchaseAmount'            => '100.00',
                'VerifyEnrollmentRequestId' => 'rand123',
                'Currency'                  => '949',
                'SuccessUrl'                => 'https://domain.com/success',
                'FailureUrl'                => 'https://domain.com/fail_url',
                'IsRecurring'               => 'false',
                'InstallmentCount'          => '2',
                'Pan'                       => '5555444433332222',
                'ExpiryDate'                => '2112',
                'BrandName'                 => '100',
            ],
        ];

        $order['installment']               = 0;
        $order['recurringFrequencyType']    = 'DAY';
        $order['recurringFrequency']        = 3;
        $order['recurringInstallmentCount'] = 2;
        $order['recurringEndDate']          = new \DateTime('2023-10-14');

        yield 'with_recurrent_payment' => [
            'order'    => $order,
            'card'     => $card,
            'expected' => [
                'MerchantId'                => '000000000111111',
                'MerchantPassword'          => '3XTgER89as',
                'MerchantType'              => 0,
                'PurchaseAmount'            => '100.00',
                'VerifyEnrollmentRequestId' => 'rand123',
                'Currency'                  => '949',
                'SuccessUrl'                => 'https://domain.com/success',
                'FailureUrl'                => 'https://domain.com/fail_url',
                'IsRecurring'               => 'true',
                'Pan'                       => '5555444433332222',
                'ExpiryDate'                => '2112',
                'BrandName'                 => '100',
                'RecurringFrequency'        => '3',
                'RecurringFrequencyType'    => 'Day',
                'RecurringInstallmentCount' => '2',
                'RecurringEndDate'          => '20231014',
            ],
        ];

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
