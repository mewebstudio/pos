<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Generator;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper
 */
class KuveytPosRequestDataMapperTest extends TestCase
{
    public KuveytPosAccount $account;

    private CreditCardInterface $card;

    private KuveytPosRequestDataMapper $requestDataMapper;

    /**
     * @return void
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../../config/pos_test.php';

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );

        $crypt                   = CryptFactory::createGatewayCrypt(KuveytPos::class, new NullLogger());
        $this->requestDataMapper = new KuveytPosRequestDataMapper($dispatcher, $crypt);
    }

    /**
     * @testWith ["pay", "Sale"]
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
     * @dataProvider create3DEnrollmentCheckRequestDataDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $txType, array $expectedData): void
    {
        $account = $this->account;
        $card    = $this->card;

        $actualData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $account,
            $order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
            $card
        );
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(KuveytPosAccount $kuveytPosAccount, array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($kuveytPosAccount, $order, $txType, $responseData);

        $this->assertEquals($expectedData, $actual);
    }


    public static function createCancelRequestDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'transaction_id'  => '298433',
                'amount'          => 1.01,
                'currency'        => PosInterface::CURRENCY_TRY,
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => '400235',
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 101,
                'MerchantId'            => '80',
                'OrderId'               => '114293600',
                'RRN'                   => '318923298433',
                'Stan'                  => '298433',
                'ProvisionNumber'       => '241839',
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'Om26dd7XpVGq0KyTJBM3TUH4fSU=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'SaleReversal',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 101,
                    'DisplayAmount'                    => 101,
                    'CancelAmount'                     => 101,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
            ],
        ];
    }

    public static function createRefundRequestDataProvider(): Generator
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'transaction_id'  => '298433',
                'amount'          => 1.01,
                'currency'        => PosInterface::CURRENCY_TRY,
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => '400235',
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 101,
                'MerchantId'            => '80',
                'OrderId'               => '114293600',
                'RRN'                   => '318923298433',
                'Stan'                  => '298433',
                'ProvisionNumber'       => '241839',
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'Om26dd7XpVGq0KyTJBM3TUH4fSU=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'PartialDrawback',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 101,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 101,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
            ],
        ];
    }

    public static function createStatusRequestDataProvider(): iterable
    {
        $startDate = new \DateTime('2022-07-08T22:44:31');
        $endDate   = new \DateTime('2023-07-08T22:44:31');
        yield [
            'order'    => [
                'id'         => '2023070849CD',
                'currency'   => PosInterface::CURRENCY_TRY,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => null,
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 0,
                'MerchantId'            => '80',
                'OrderId'               => 0,
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'RwQ5Sfc6D4Ovy7jvQgf5jGA/rOk=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'GetMerchantOrderDetail',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 0,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 0,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
                'MerchantOrderId'       => '2023070849CD',
                'StartDate'             => '2022-07-08T22:44:31',
                'EndDate'               => '2023-07-08T22:44:31',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        $account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $order = [
            'id'          => '2020110828BC',
            'amount'      => 1,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            [
                'account'      => $account,
                'order'        => $order,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'MD'              => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'VPosMessage'     => [
                        'InstallmentCount'    => '0',
                        'Amount'              => '100',
                        'CurrencyCode'        => '0949',
                        'OkUrl'               => 'http://localhost/response',
                        'FailUrl'             => 'http://localhost/response',
                        'OrderId'             => '86297530',
                        'MerchantOrderId'     => '2020110828BC',
                        'TransactionSecurity' => '3',
                        'MerchantId'          => '****',
                        'SubMerchantId'       => '0',
                        'CustomerId'          => '*****',
                        'UserName'            => 'fapapi',
                        'HashPassword'        => 'Hiorgg24rNeRdHUvMCg//mOJn4U=',
                        'CardNumber'          => '***********1609',
                    ],
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'OrderId'         => '86297530',
                    'MerchantOrderId' => '2020110828BC',
                    'HashData'        => 'ucejRvHjCbuPXagyoweFLnJfSJg=',
                    'BusinessKey'     => '20220845654324600000140459',
                ],
                'expected'     => [
                    'APIVersion'                   => KuveytPosRequestDataMapper::API_VERSION,
                    'HashData'                     => '9nMtjMKzb7y/hOC4RiDZXkR8uqE=',
                    'MerchantId'                   => '80',
                    'CustomerId'                   => '400235',
                    'UserName'                     => 'apiuser',
                    'CustomerIPAddress'            => '127.0.0.1',
                    'KuveytTurkVPosAdditionalData' => [
                        'AdditionalData' => [
                            'Key'  => 'MD',
                            'Data' => '67YtBfBRTZ0XBKnAHi8c/A==',
                        ],
                    ],
                    'TransactionType'              => 'Sale',
                    'InstallmentCount'             => '0',
                    'Amount'                       => '100',
                    'DisplayAmount'                => 10000,
                    'CurrencyCode'                 => '0949',
                    'MerchantOrderId'              => '2020110828BC',
                    'TransactionSecurity'          => '3',
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
                    'ip'          => '127.0.0.1',
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'expectedData' => [
                    'APIVersion'          => KuveytPosRequestDataMapper::API_VERSION,
                    'MerchantId'          => '80',
                    'UserName'            => 'apiuser',
                    'CustomerId'          => '400235',
                    'HashData'            => 'aEW1KcKuzz2e+oeU36kyEnC65/4=',
                    'TransactionType'     => 'Sale',
                    'TransactionSecurity' => '3',
                    'InstallmentCount'    => '0',
                    'Amount'              => 1001,
                    'DisplayAmount'       => 1001,
                    'CurrencyCode'        => '0949',
                    'MerchantOrderId'     => '2020110828BC',
                    'OkUrl'               => 'http://localhost/finansbank-payfor/3d/success.php',
                    'FailUrl'             => 'http://localhost/finansbank-payfor/3d/fail.php',
                    'CardHolderName'      => 'John Doe',
                    'CardNumber'          => '4155650100416111',
                    'CardType'            => 'Visa',
                    'CardExpireDateYear'  => '25',
                    'CardExpireDateMonth' => '01',
                    'CardCVV2'            => '123',
                ],
            ],
        ];
    }
}
