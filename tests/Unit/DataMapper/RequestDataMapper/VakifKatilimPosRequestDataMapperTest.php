<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class VakifKatilimPosRequestDataMapperTest extends TestCase
{
    private KuveytPosAccount $account;

    private CreditCardInterface $card;

    private VakifKatilimPosRequestDataMapper $requestDataMapper;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createKuveytPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
        );

        $this->card = CreditCardFactory::create(
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
        );

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new VakifKatilimPosRequestDataMapper($this->dispatcher, $this->crypt);
    }

    /**
     * @return void
     */
    public function testFormatAmount(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame(0, $method->invokeArgs($this->requestDataMapper, [0]));
        $this->assertSame(0, $method->invokeArgs($this->requestDataMapper, [0.0]));
        $this->assertSame(1025, $method->invokeArgs($this->requestDataMapper, [10.25]));
        $this->assertSame(1000, $method->invokeArgs($this->requestDataMapper, [10.00]));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapCurrency');
        $method->setAccessible(true);
        $this->assertSame('0949', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('0978', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
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
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $gatewayURL,
        string $txType,
        string $paymentModel,
        array  $expected
    ): void {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $hashCalculationData = $expected['inputs'];
            unset($hashCalculationData['HashPassword']);

            $this->crypt->expects(self::once())
                ->method('hashString')
                ->with($this->account->getStoreKey())
                ->willReturn($expected['inputs']['HashPassword']);
        }

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($this->account->getStoreKey())
            ->willReturn($expectedData['HashPassword']);

        $hashCalculationData = $expectedData;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['HashData']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createNonSecurePostAuthPaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($this->account->getStoreKey())
            ->willReturn($expectedData['HashPassword']);

        $hashCalculationData = $expectedData;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['HashData']);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData(
            $this->account,
            $order,
        );

        $this->assertSame($expectedData, $actual);
    }


    /**
     * @dataProvider create3DEnrollmentCheckRequestDataDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $txType, array $expectedData): void
    {
        $account = $this->account;
        $card    = $this->card;

        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($account->getStoreKey())
            ->willReturn($expectedData['HashPassword']);

        $hashCalculationData = $expectedData;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($account, $hashCalculationData)
            ->willReturn($expectedData['HashData']);

        $actualData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $account,
            $order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
            $card
        );

        ksort($actualData);
        ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($this->account->getStoreKey())
            ->willReturn($expected['HashPassword']);

        $hashCalculationData = $expected;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['HashData']);

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($this->account->getStoreKey())
            ->willReturn($expected['HashPassword']);

        $hashCalculationData = $expected;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['HashData']);

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createHistoryRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $order, array $expected): void
    {
        $hashCalculationData = $expected;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['HashData']);

        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider createOrderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $hashCalculationData = $expected;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['HashData']);

        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $hashCalculationData = $expected;
        unset($hashCalculationData['HashData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['HashData']);

        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(KuveytPosAccount $kuveytPosAccount, array $order, string $txType, array $responseData, array $expectedData): void
    {
        $hashCalculationData             = $expectedData;
        $hashCalculationData['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($kuveytPosAccount, $hashCalculationData)
            ->willReturn($expectedData['HashData']);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($kuveytPosAccount, $order, $txType, $responseData);

        ksort($expectedData);
        ksort($actual);
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        if (!isset($requestData['HashData'])) {
            $this->crypt->expects(self::once())
                ->method('createHash')
                ->willReturn($expectedData['HashData']);
        }

        $this->crypt->expects(self::once())
            ->method('hashString')
            ->with($this->account->getStoreKey())
            ->willReturn($expectedData['HashPassword']);

        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_mail_order_sale' => [
            'request_data' => [
                'MerchantOrderId'                  => '2126497214',
                'InstallmentCount'                 => '0',
                'Amount'                           => '120',
                'DisplayAmount'                    => '120',
                'FECAmount'                        => '0',
                'FECCurrencyCode'                  => '0949',
                'Addresses'                        => [
                    'VPosAddressContract' => [
                        'Type'        => '1',
                        'Name'        => 'Mahmut Sami YAZAR',
                        'PhoneNumber' => '324234234234',
                        'OrderId'     => '0',
                        'AddressId'   => '12',
                        'Email'       => 'mahmutsamiyazar@hotmail.com',
                    ],
                ],
                'CardNumber'                       => '5353550000958906',
                'CardExpireDateYear'               => '23',
                'CardExpireDateMonth'              => '01',
                'CardCVV2'                         => '741',
                'CardHolderName'                   => 'Hasan Karacan',
                'DebtId'                           => '0',
                'SurchargeAmount'                  => '0',
                'SGKDebtAmount'                    => '0',
                'InstallmentMaturityCommisionFlag' => '0',
                'TransactionSecurity'              => '1',
                'CardGuid'                         => 'AA9588EF350C480FBE5CAD40A463AF00',
            ],
            'expected'     => [
                'APIVersion'                       => '1.0.0',
                'Addresses'                        => [
                    'VPosAddressContract' => [
                        'Type'        => '1',
                        'Name'        => 'Mahmut Sami YAZAR',
                        'PhoneNumber' => '324234234234',
                        'OrderId'     => '0',
                        'AddressId'   => '12',
                        'Email'       => 'mahmutsamiyazar@hotmail.com',
                    ],
                ],
                'Amount'                           => '120',
                'CardCVV2'                         => '741',
                'CardExpireDateMonth'              => '01',
                'CardExpireDateYear'               => '23',
                'CardGuid'                         => 'AA9588EF350C480FBE5CAD40A463AF00',
                'CardHolderName'                   => 'Hasan Karacan',
                'CardNumber'                       => '5353550000958906',
                'CustomerId'                       => '11111',
                'DebtId'                           => '0',
                'DisplayAmount'                    => '120',
                'FECAmount'                        => '0',
                'FECCurrencyCode'                  => '0949',
                'HashData'                         => 'fskfjslfjss',
                'HashPassword'                     => 'lf;slkddskf;sa',
                'InstallmentCount'                 => '0',
                'InstallmentMaturityCommisionFlag' => '0',
                'MerchantId'                       => '1',
                'MerchantOrderId'                  => '2126497214',
                'SGKDebtAmount'                    => '0',
                'SubMerchantId'                    => '0',
                'SurchargeAmount'                  => '0',
                'TransactionSecurity'              => '1',
                'UserName'                         => 'APIUSER',
            ],
        ];

        yield 'with_account_data_mail_order_sale' => [
            'request_data' => [
                'APIVersion'                       => '1.0.0',
                'Addresses'                        => [
                    'VPosAddressContract' => [
                        'Type'        => '1',
                        'Name'        => 'Mahmut Sami YAZAR',
                        'PhoneNumber' => '324234234234',
                        'OrderId'     => '0',
                        'AddressId'   => '12',
                        'Email'       => 'mahmutsamiyazar@hotmail.com',
                    ],
                ],
                'Amount'                           => '120',
                'CardCVV2'                         => '741',
                'CardExpireDateMonth'              => '01',
                'CardExpireDateYear'               => '23',
                'CardGuid'                         => 'AA9588EF350C480FBE5CAD40A463AF00',
                'CardHolderName'                   => 'Hasan Karacan',
                'CardNumber'                       => '5353550000958906',
                'DebtId'                           => '0',
                'DisplayAmount'                    => '120',
                'FECAmount'                        => '0',
                'FECCurrencyCode'                  => '0949',
                'InstallmentCount'                 => '0',
                'InstallmentMaturityCommisionFlag' => '0',
                'MerchantOrderId'                  => '2126497214',
                'SGKDebtAmount'                    => '0',
                'SurchargeAmount'                  => '0',
                'TransactionSecurity'              => '1',
                'SubMerchantId'                    => '0',
                'CustomerId'                       => '11111xxx',
                'HashPassword'                     => 'lf;slkddskf;saxxx',
                'MerchantId'                       => '1xxx',
                'UserName'                         => 'APIUSERXXX',
                'HashData'                         => 'fskfjslfjsszzzz',
            ],
            'expected'     => [
                'APIVersion'                       => '1.0.0',
                'Addresses'                        => [
                    'VPosAddressContract' => [
                        'Type'        => '1',
                        'Name'        => 'Mahmut Sami YAZAR',
                        'PhoneNumber' => '324234234234',
                        'OrderId'     => '0',
                        'AddressId'   => '12',
                        'Email'       => 'mahmutsamiyazar@hotmail.com',
                    ],
                ],
                'Amount'                           => '120',
                'CardCVV2'                         => '741',
                'CardExpireDateMonth'              => '01',
                'CardExpireDateYear'               => '23',
                'CardGuid'                         => 'AA9588EF350C480FBE5CAD40A463AF00',
                'CardHolderName'                   => 'Hasan Karacan',
                'CardNumber'                       => '5353550000958906',
                'DebtId'                           => '0',
                'DisplayAmount'                    => '120',
                'FECAmount'                        => '0',
                'FECCurrencyCode'                  => '0949',
                'InstallmentCount'                 => '0',
                'InstallmentMaturityCommisionFlag' => '0',
                'MerchantOrderId'                  => '2126497214',
                'SGKDebtAmount'                    => '0',
                'SurchargeAmount'                  => '0',
                'TransactionSecurity'              => '1',
                'SubMerchantId'                    => '0',
                'CustomerId'                       => '11111xxx',
                'HashPassword'                     => 'lf;slkddskf;saxxx',
                'MerchantId'                       => '1xxx',
                'UserName'                         => 'APIUSERXXX',
                'HashData'                         => 'fskfjslfjsszzzz',
            ],
        ];
    }

    public static function createCancelRequestDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'amount'          => 1.01,
            ],
            'expected' => [
                'CustomerId'      => '11111',
                'Amount'          => 101,
                'MerchantId'      => '1',
                'OrderId'         => '114293600',
                'UserName'        => 'APIUSER',
                'SubMerchantId'   => '0',
                'HashPassword'    => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId' => '2023070849CD',
                'PaymentType'     => '1',
                'HashData'        => '/fcrfEy2juD7L/aTujiLgtJg23M=',
            ],
        ];

        yield [
            'order'    => [
                'id'               => '2023070849CD',
                'remote_order_id'  => '114293600',
                'amount'           => 1.01,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'CustomerId'      => '11111',
                'Amount'          => 101,
                'MerchantId'      => '1',
                'OrderId'         => '114293600',
                'UserName'        => 'APIUSER',
                'SubMerchantId'   => '0',
                'HashPassword'    => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId' => '2023070849CD',
                'PaymentType'     => '1',
                'HashData'        => '/fcrfEy2juD7L/aTujiLgtJg23M=',
            ],
        ];

        yield [
            'order'    => [
                'id'               => '2023070849CD',
                'remote_order_id'  => '114293600',
                'amount'           => 1.01,
                'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            ],
            'expected' => [
                'CustomerId'      => '11111',
                'MerchantId'      => '1',
                'OrderId'         => '114293600',
                'UserName'        => 'APIUSER',
                'SubMerchantId'   => '0',
                'HashPassword'    => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId' => '2023070849CD',
                'PaymentType'     => '1',
                'HashData'        => '2JEWD55cC2JtLJIxM8KDRz2f6hU=',
            ],
        ];
    }

    public static function createRefundRequestDataProvider(): Generator
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'amount'          => 1.01,
            ],
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
            'expected' => [
                'MerchantId'      => '1',
                'CustomerId'      => '11111',
                'UserName'        => 'APIUSER',
                'SubMerchantId'   => '0',
                'HashPassword'    => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId' => '2023070849CD',
                'OrderId'         => '114293600',
                'HashData'        => '2JEWD55cC2JtLJIxM8KDRz2f6hU=',
            ],
        ];
    }

    public static function createStatusRequestDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id' => 'order-123',
            ],
            'expected' => [
                'MerchantId'      => '1',
                'CustomerId'      => '11111',
                'UserName'        => 'APIUSER',
                'SubMerchantId'   => '0',
                'MerchantOrderId' => 'order-123',
                'HashData'        => '4oNmzFPMeC/tOK8i/XCPNy4W+FU=',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        $account = AccountFactory::createKuveytPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
        );

        $order = [
            'id'          => '2020110828BC',
            'amount'      => 1,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
        ];

        return [
            [
                'account'      => $account,
                'order'        => $order,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'MD'              => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'OrderId'         => '86297530',
                    'MerchantOrderId' => '2020110828BC',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'HashData'        => 'ucejRvHjCbuPXagyoweFLnJfSJg=',
                ],
                'expected'     => [
                    'APIVersion'          => VakifKatilimPosRequestDataMapper::API_VERSION,
                    'HashData'            => 'sFxxO809/N3Yif4p/js1UKFMRro=',
                    'MerchantId'          => '1',
                    'CustomerId'          => '11111',
                    'UserName'            => 'APIUSER',
                    'InstallmentCount'    => '0',
                    'Amount'              => 100,
                    'MerchantOrderId'     => '2020110828BC',
                    'TransactionSecurity' => '3',
                    'SubMerchantId'       => '0',
                    'OkUrl'               => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'             => 'http://localhost/finansbank-payfor/3d/response.php',
                    'AdditionalData'      => [
                        'AdditionalDataList' => [
                            'VPosAdditionalData' => [
                                'Key'  => 'MD',
                                'Data' => '67YtBfBRTZ0XBKnAHi8c/A==',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function create3DEnrollmentCheckRequestDataDataProvider(): array
    {
        return [
            [
                'order'        => [
                    'id'          => '2020110828BC',
                    'amount'      => 10.01,
                    'installment' => '0',
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'success_url' => 'http://localhost/finansbank-payfor/3d/success.php',
                    'fail_url'    => 'http://localhost/finansbank-payfor/3d/fail.php',
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'expectedData' => [
                    'APIVersion'          => VakifKatilimPosRequestDataMapper::API_VERSION,
                    'MerchantId'          => '1',
                    'UserName'            => 'APIUSER',
                    'CustomerId'          => '11111',
                    'HashData'            => 'xAV9RXTP58Hy+8KYe1VgYDaZFqs=',
                    'TransactionSecurity' => '3',
                    'InstallmentCount'    => '0',
                    'Amount'              => 1001,
                    'DisplayAmount'       => 1001,
                    'FECCurrencyCode'     => '0949',
                    'MerchantOrderId'     => '2020110828BC',
                    'OkUrl'               => 'http://localhost/finansbank-payfor/3d/success.php',
                    'FailUrl'             => 'http://localhost/finansbank-payfor/3d/fail.php',
                    'CardHolderName'      => 'John Doe',
                    'CardNumber'          => '4155650100416111',
                    'CardExpireDateYear'  => '25',
                    'CardExpireDateMonth' => '01',
                    'CardCVV2'            => '123',
                    'SubMerchantId'       => '0',
                    'HashPassword'        => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                ],
            ],
        ];
    }


    public static function nonSecurePaymentRequestDataDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'          => '123',
                'amount'      => 10.0,
                'installment' => 0,
                'currency'    => PosInterface::CURRENCY_TRY,
            ],
            'expected' => [
                'MerchantId'          => '1',
                'CustomerId'          => '11111',
                'UserName'            => 'APIUSER',
                'SubMerchantId'       => '0',
                'APIVersion'          => '1.0.0',
                'HashPassword'        => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId'     => '123',
                'InstallmentCount'    => '0',
                'Amount'              => 1000,
                'FECCurrencyCode'     => '0949',
                'CurrencyCode'        => '0949',
                'TransactionSecurity' => '5',
                'CardNumber'          => '4155650100416111',
                'CardExpireDateYear'  => '25',
                'CardExpireDateMonth' => '01',
                'CardCVV2'            => '123',
                'CardHolderName'      => 'John Doe',
                'HashData'            => 'AYOjSzXn6dgwiV3U0vXzNTWlO8g=',
            ],
        ];

        yield 'withInstallment' => [
            'order'    => [
                'id'          => '123',
                'amount'      => 10.0,
                'currency'    => PosInterface::CURRENCY_TRY,
                'installment' => 3,
            ],
            'expected' => [
                'MerchantId'          => '1',
                'CustomerId'          => '11111',
                'UserName'            => 'APIUSER',
                'SubMerchantId'       => '0',
                'APIVersion'          => '1.0.0',
                'HashPassword'        => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId'     => '123',
                'InstallmentCount'    => '3',
                'Amount'              => 1000,
                'FECCurrencyCode'     => '0949',
                'CurrencyCode'        => '0949',
                //'PaymentType'         => '1',
                'TransactionSecurity' => '5',
                'CardNumber'          => '4155650100416111',
                'CardExpireDateYear'  => '25',
                'CardExpireDateMonth' => '01',
                'CardCVV2'            => '123',
                'CardHolderName'      => 'John Doe',
                'HashData'            => 'AYOjSzXn6dgwiV3U0vXzNTWlO8g=',
            ],
        ];
    }

    public static function createNonSecurePostAuthPaymentRequestDataDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'              => '123',
                'remote_order_id' => 'remote-123',
                'ip'              => '127.0.0.1',
            ],
            'expected' => [
                'MerchantId'        => '1',
                'CustomerId'        => '11111',
                'UserName'          => 'APIUSER',
                'SubMerchantId'     => '0',
                'HashPassword'      => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                'MerchantOrderId'   => '123',
                'OrderId'           => 'remote-123',
                'CustomerIPAddress' => '127.0.0.1',
                'HashData'          => 'K0LvOf07C/ayD53wWiykcUCZPc8=',
            ],
        ];
    }

    public static function createHistoryRequestDataProvider(): Generator
    {
        yield [
            'order'    => [
                'start_date' => (new \DateTime('2024-03-30')),
                'end_date'   => (new \DateTime('2024-03-31')),
                'page'       => 1,
                'page_size'  => 10,
            ],
            'expected' => [
                'MerchantId'    => '1',
                'CustomerId'    => '11111',
                'UserName'      => 'APIUSER',
                'SubMerchantId' => '0',
                'StartDate'     => '2024-03-30',
                'EndDate'       => '2024-03-31',
                'LowerLimit'    => 0,
                'UpperLimit'    => 10,
                'ProvNumber'    => null,
                'OrderStatus'   => null,
                'TranResult'    => null,
                'OrderNo'       => null,
                'HashData'      => '58xhdJGlgIZtsid8cvSDlr8EItk=',
            ],
        ];
    }

    public static function createOrderHistoryRequestDataProvider(): Generator
    {
        yield [
            'order'    => [
                'start_date' => (new \DateTime('2024-03-30')),
                'end_date'   => (new \DateTime('2024-03-31')),
                'auth_code'  => '896626',
            ],
            'expected' => [
                'MerchantId'    => '1',
                'CustomerId'    => '11111',
                'UserName'      => 'APIUSER',
                'SubMerchantId' => '0',
                'StartDate'     => '2024-03-30',
                'EndDate'       => '2024-03-31',
                'LowerLimit'    => 0,
                'UpperLimit'    => 100,
                'ProvNumber'    => '896626',
                'OrderStatus'   => null,
                'TranResult'    => null,
                'OrderNo'       => null,
                'HashData'      => '58xhdJGlgIZtsid8cvSDlr8EItk=',
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
        ];

        return [
            '3d_host' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => [
                    'gateway' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
                    'method'  => 'POST',
                    'inputs'  => [
                        'UserName'        => 'APIUSER',
                        'HashPassword'    => 'h58bUB83xQz2/21SUeOemUgkF5U=',
                        'MerchantId'      => '1',
                        'MerchantOrderId' => 'order222',
                        'Amount'          => 10025,
                        'FECCurrencyCode' => '0949',
                        'OkUrl'           => 'https://domain.com/success',
                        'FailUrl'         => 'https://domain.com/fail_url',
                        'PaymentType'     => '1',
                    ],
                ],
            ],
            '3d'      => [
                'order'        => [
                    'ResponseCode'    => '00',
                    'ResponseMessage' => '',
                    'ProvisionNumber' => 'prov-123',
                    'MerchantOrderId' => 'order-123',
                    'OrderId'         => 'bank-123',
                    'RRN'             => 'rrn-123',
                    'Stan'            => 'stan-123',
                    'HashData'        => 'hash-123',
                    'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                ],
                'gatewayUrl'   => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => [
                    'gateway' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ResponseCode'    => '00',
                        'ResponseMessage' => '',
                        'ProvisionNumber' => 'prov-123',
                        'MerchantOrderId' => 'order-123',
                        'OrderId'         => 'bank-123',
                        'RRN'             => 'rrn-123',
                        'Stan'            => 'stan-123',
                        'HashData'        => 'hash-123',
                        'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                    ],
                ],
            ],
        ];
    }
}
