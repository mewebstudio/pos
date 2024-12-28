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
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class PayFlexV4PosRequestDataMapperTest extends TestCase
{
    public PayFlexAccount $account;

    private PayFlexV4PosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new PayFlexV4PosRequestDataMapper($this->dispatcher, $this->crypt);
    }

    /**
     * @testWith ["pay", "Sale"]
     * ["pre", "Auth"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["Sale"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->requestDataMapper->mapTxType($txType);
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
        $this->assertSame($expected, $actual);
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
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider createNonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, CreditCardInterface $creditCard, string $txType, array $expected): void
    {
        $actualData = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $creditCard
        );

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider createNonSecurePostAuthPaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createCancelRequestDataDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $actualData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actualData);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider create3DFormDataDataProvider
     */
    public function testCreate3DFormData(array $bankResponse, array $expected): void
    {
        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $actualData = $this->requestDataMapper->create3DFormData(
            null,
            null,
            null,
            null,
            null,
            null,
            $bankResponse
        );

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider createStatusRequestDataDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    public function testCreateOrderHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account, []);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_campaign_search' => [
            'request_data' => [
                'TransactionType' => 'CampaignSearch',
            ],
            'expected' => [
                'MerchantId'      => '000000000111111',
                'Password'        => '3XTgER89as',
                'TerminalNo'      => 'VP999999',
                'TransactionType' => 'CampaignSearch',
            ],
        ];

        yield 'with_account_data_campaign_search' => [
            'request_data' => [
                'MerchantId'      => '000000000111111zz',
                'Password'        => '3XTgER89aszzz',
                'TerminalNo'      => 'VP999999zz',
                'TransactionType' => 'CampaignSearchzz',
            ],
            'expected'     => [
                'MerchantId'      => '000000000111111zz',
                'Password'        => '3XTgER89aszzz',
                'TerminalNo'      => 'VP999999zz',
                'TransactionType' => 'CampaignSearchzz',
            ],
        ];
    }

    public static function createStatusRequestDataDataProvider(): array
    {
        return [
            'only_with_order_id'      => [
                'order'    => [
                    'id' => 'order222',
                ],
                'expected' => [
                    'MerchantCriteria'    => [
                        'HostMerchantId'   => '000000000111111',
                        'MerchantPassword' => '3XTgER89as',
                    ],
                    'TransactionCriteria' => [
                        'TransactionId' => '',
                        'OrderId'       => 'order222',
                        'AuthCode'      => '',
                    ],
                ],
            ],
            'with_order_id_and_tx_id' => [
                'order'    => [
                    'id'             => 'order222',
                    'transaction_id' => 'tx222',
                ],
                'expected' => [
                    'MerchantCriteria'    => [
                        'HostMerchantId'   => '000000000111111',
                        'MerchantPassword' => '3XTgER89as',
                    ],
                    'TransactionCriteria' => [
                        'TransactionId' => 'tx222',
                        'OrderId'       => 'order222',
                        'AuthCode'      => '',
                    ],
                ],
            ],
        ];
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

    public static function createCancelRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'transaction_id' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'ip'             => '127.0.0.1',
                ],
                'expected' => [
                    'MerchantId'             => '000000000111111',
                    'Password'               => '3XTgER89as',
                    'TransactionType'        => 'Cancel',
                    'ReferenceTransactionId' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'ClientIp'               => '127.0.0.1',
                ],
            ],
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

        $card = CreditCardFactory::create(
            '5555444433332222',
            '2021',
            '12',
            '122',
            'ahmet',
            CreditCardInterface::CARD_TYPE_VISA
        );

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
                'Pan'                       => '5555444433332222',
                'ExpiryDate'                => '2112',
                'BrandName'                 => '100',
                'IsRecurring'               => 'false',
                'InstallmentCount'          => '2',
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
                'Pan'                       => '5555444433332222',
                'ExpiryDate'                => '2112',
                'BrandName'                 => '100',
                'IsRecurring'               => 'true',
                'RecurringFrequency'        => '3',
                'RecurringFrequencyType'    => 'Month',
                'RecurringInstallmentCount' => '2',
                'RecurringEndDate'          => '20240414',
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund'    => [
                'order'    => [
                    'transaction_id' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'amount'         => 5,
                    'ip'             => '127.0.0.1',
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'ClientIp'               => '127.0.0.1',
                    'CurrencyAmount'         => '5.00',
                    'MerchantId'             => '000000000111111',
                    'Password'               => '3XTgER89as',
                    'ReferenceTransactionId' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'TransactionType'        => 'Refund',
                ],
            ],
            'partial_refund' => [
                'order'    => [
                    'transaction_id' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'amount'         => 5,
                    'ip'             => '127.0.0.1',
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'expected' => [
                    'ClientIp'               => '127.0.0.1',
                    'CurrencyAmount'         => '5.00',
                    'MerchantId'             => '000000000111111',
                    'Password'               => '3XTgER89as',
                    'ReferenceTransactionId' => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'TransactionType'        => 'Refund',
                ],
            ],
        ];
    }

    public static function createNonSecurePaymentRequestDataDataProvider(): array
    {
        $creditCard = CreditCardFactory::create(
            '5555444433332222',
            '2021',
            '12',
            '122',
            'ahmet',
            CreditCardInterface::CARD_TYPE_VISA
        );

        return [
            [
                'order'    => [
                    'id'     => 'order123',
                    'amount' => 5,
                    'ip'     => '127.0.0.1',
                ],
                'card'     => $creditCard,
                'tx_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'MerchantId'              => '000000000111111',
                    'Password'                => '3XTgER89as',
                    'TerminalNo'              => 'VP999999',
                    'TransactionType'         => 'Sale',
                    'OrderId'                 => 'order123',
                    'CurrencyAmount'          => '5.00',
                    'CurrencyCode'            => '949',
                    'ClientIp'                => '127.0.0.1',
                    'TransactionDeviceSource' => '0',
                    'Pan'                     => $creditCard->getNumber(),
                    'Expiry'                  => '202112',
                    'Cvv'                     => $creditCard->getCvv(),
                ],
            ],
        ];
    }

    public static function createNonSecurePostAuthPaymentRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'     => 'order123',
                    'amount' => 1000,
                    'ip'     => '127.0.0.1',
                ],
                'expected' => [
                    'MerchantId'             => '000000000111111',
                    'Password'               => '3XTgER89as',
                    'TerminalNo'             => 'VP999999',
                    'TransactionType'        => 'Capture',
                    'ReferenceTransactionId' => 'order123',
                    'CurrencyAmount'         => '1000.00',
                    'CurrencyCode'           => '949',
                    'ClientIp'               => '127.0.0.1',
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public static function create3DFormDataDataProvider(): array
    {
        return [
            [
                'response' => self::getSampleEnrollmentSuccessResponseDataProvider()['Message']['VERes'],
                'expected' => [
                    'gateway' => 'http',
                    'method'  => 'POST',
                    'inputs'  => [

                        'PaReq'   => 'PaReq2',
                        'TermUrl' => 'TermUrl2',
                        'MD'      => 'MD3',
                    ],
                ],
            ],
        ];
    }
}
