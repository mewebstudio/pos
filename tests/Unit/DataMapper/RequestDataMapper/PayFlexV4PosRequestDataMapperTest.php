<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper
 */
class PayFlexV4PosRequestDataMapperTest extends TestCase
{
    public PayFlexAccount $account;

    private CreditCardInterface $card;

    private PayFlexV4PosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../../config/pos_test.php';

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
            'ip'          => '127.0.0.1',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new PayFlexV4PosRequestDataMapper($dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testFormatAmount(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame('1000.00', $method->invokeArgs($this->requestDataMapper, [1000]));
    }

    /**
     * @testWith ["MONTH", "Month"]
     *            ["Month", "Month"]
     */
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapRecurringFrequency');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$frequency]));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapCurrency');
        $method->setAccessible(true);
        $this->assertSame('949', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('978', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
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
    public function testMapInstallment($installment, $expected): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapInstallment');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$installment]));
    }

    /**
     * @dataProvider threeDPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $gatewayResponse, CreditCardInterface $creditCard, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($posAccount, $order, $txType, $gatewayResponse, $creditCard);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider threeDPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestDataWithoutCard(AbstractPosAccount $posAccount, array $order, string $txType, array $gatewayResponse): void
    {
        $this->expectException(\LogicException::class);
        $this->requestDataMapper->create3DPaymentRequestData($posAccount, $order, $txType, $gatewayResponse);
    }

    /**
     * @dataProvider three3DEnrollmentRequestDataDataProvider
     */
    public function testCreate3DEnrollmentCheckData(array $order, ?CreditCardInterface $creditCard, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['VerifyEnrollmentRequestId']);

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $creditCard);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData(): void
    {
        $order           = $this->order;
        $txType          = PosInterface::TX_TYPE_PAY_AUTH;
        $order['amount'] = 1000;

        $expectedValue = $this->getSampleNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);
        $actualData    = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(): void
    {
        $order           = $this->order;
        $order['amount'] = 1000;

        $expectedValue = $this->getSampleNonSecurePaymentPostRequestData($this->account, $order);
        $actual        = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actual);
    }

    public function testCreateCancelRequestData(): void
    {
        $order                   = $this->order;
        $order['transaction_id'] = '7022b92e-3aa1-44fb-86d4-33658c700c80';

        $expectedValue = $this->getSampleCancelRequestData();
        $actualData    = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actualData);
    }

    public function testCreateRefundRequestData(): void
    {
        $order                   = $this->order;
        $order['transaction_id'] = '7022b92e-3aa1-44fb-86d4-33658c700c80';
        $order['amount']         = 1000;

        $expectedValue = $this->getSampleRefundRequestData();
        $actualData    = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $this->assertEquals($expectedValue, $actualData);
    }

    /**
     * @return void
     */
    public function testCreate3DFormData(): void
    {
        $expectedValue = $this->getSample3DFormDataFromEnrollmentResponse();
        $actualData    = $this->requestDataMapper->create3DFormData(
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
            'ErrorMessage'     => 'some error',
            'Message'          => [
                'VERes' => [
                    'Status'  => 'Y',
                    'PaReq'   => 'PaReq2',
                    'TermUrl' => 'TermUrl2',
                    'MD'      => 'MD3',
                    'ACSUrl'  => 'http',
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

    private function getSampleCancelRequestData(): array
    {
        return [
            'MerchantId'             => '000000000111111',
            'Password'               => '3XTgER89as',
            'TransactionType'        => 'Cancel',
            'ReferenceTransactionId' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
            'ClientIp'               => '127.0.0.1',
        ];
    }

    public static function threeDPaymentRequestDataDataProvider(): \Generator
    {
        $account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'ip'          => '127.0.0.1',
        ];

        $responseData = [
            'Eci'                       => '05',
            'Cavv'                      => 'AAABCYaRIwAAAVQ1gpEjAAAAAAA=',
            'VerifyEnrollmentRequestId' => 'ce06048a3e9c0cd1d437803fb38b5ad0',
        ];

        $card = new CreditCard('5555444433332222', new \DateTimeImmutable('2021-12-01'), '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);

        yield 'no_installment' => [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => $responseData,
            'card'         => $card,
            'expected'     => [
                'MerchantId'              => '000000000111111',
                'Password'                => '3XTgER89as',
                'TerminalNo'              => 'VP999999',
                'TransactionType'         => 'Sale',
                'TransactionId'           => 'order222',
                'CurrencyAmount'          => '100.00',
                'CurrencyCode'            => '949',
                'ECI'                     => '05',
                'CAVV'                    => 'AAABCYaRIwAAAVQ1gpEjAAAAAAA=',
                'MpiTransactionId'        => 'ce06048a3e9c0cd1d437803fb38b5ad0',
                'OrderId'                 => 'order222',
                'ClientIp'                => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'CardHoldersName'         => 'ahmet',
                'Cvv'                     => '122',
                'Pan'                     => '5555444433332222',
                'Expiry'                  => '202112',
            ],
        ];

        $order['installment'] = 3;

        yield 'with_installment' => [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => $responseData,
            'card'         => $card,
            'expected'     => [
                'MerchantId'              => '000000000111111',
                'Password'                => '3XTgER89as',
                'TerminalNo'              => 'VP999999',
                'TransactionType'         => 'Sale',
                'TransactionId'           => 'order222',
                'CurrencyAmount'          => '100.00',
                'CurrencyCode'            => '949',
                'ECI'                     => '05',
                'CAVV'                    => 'AAABCYaRIwAAAVQ1gpEjAAAAAAA=',
                'MpiTransactionId'        => 'ce06048a3e9c0cd1d437803fb38b5ad0',
                'OrderId'                 => 'order222',
                'ClientIp'                => '127.0.0.1',
                'TransactionDeviceSource' => '0',
                'CardHoldersName'         => 'ahmet',
                'Cvv'                     => '122',
                'Pan'                     => '5555444433332222',
                'Expiry'                  => '202112',
                'NumberOfInstallments'    => '3',
            ],
        ];
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
            'ip'          => '127.0.0.1',
        ];

        $card = new CreditCard('5555444433332222', new \DateTimeImmutable('2021-12-01'), '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);

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

        $order['installment'] = 0;
        $order['recurring']   = [
            'frequency'     => 3,
            'frequencyType' => 'MONTH',
            'installment'   => 2,
            'endDate'       => (new \DateTimeImmutable('2023-10-14'))->modify("+6 MONTH"),
        ];

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
                'RecurringFrequencyType'    => 'Month',
                'RecurringInstallmentCount' => '2',
                'RecurringEndDate'          => '20240414',
            ],
        ];
    }

    /**
     * @param PayFlexAccount      $posAccount
     * @param array               $order
     * @param string              $txType
     * @param CreditCardInterface $creditCard
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        return [
            'MerchantId'              => $posAccount->getClientId(),
            'Password'                => $posAccount->getPassword(),
            'TerminalNo'              => $posAccount->getTerminalId(),
            'TransactionType'         => $this->requestDataMapper->mapTxType($txType),
            'OrderId'                 => $order['id'],
            'CurrencyAmount'          => '1000.00',
            'CurrencyCode'            => 949,
            'ClientIp'                => $order['ip'],
            'TransactionDeviceSource' => 0,
            'Pan'                     => $creditCard->getNumber(),
            'Expiry'                  => '202112',
            'Cvv'                     => $creditCard->getCvv(),
        ];
    }

    /**
     * @param PayFlexAccount $posAccount
     * @param array          $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'MerchantId'             => $posAccount->getClientId(),
            'Password'               => $posAccount->getPassword(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'TransactionType'        => 'Capture',
            'ReferenceTransactionId' => $order['id'],
            'CurrencyAmount'         => '1000.00',
            'CurrencyCode'           => '949',
            'ClientIp'               => $order['ip'],
        ];
    }

    private function getSampleRefundRequestData(): array
    {
        return [
            'MerchantId'             => '000000000111111',
            'Password'               => '3XTgER89as',
            'TransactionType'        => 'Refund',
            'ReferenceTransactionId' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
            'ClientIp'               => '127.0.0.1',
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
