<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Generator;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
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

    private array $order;

    /**
     * @return void
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->card = CreditCardFactory::create(
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
     * @return void
     */
    public function testFormatAmount()
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
    public function testMapCurrency()
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
    public function testMapInstallment($installment, $expected)
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapInstallment');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$installment]));
    }

    /**
     * @return void
     */
    public function testCompose3DFormData()
    {
        $account = $this->account;
        $card    = $this->card;

        $inputs = [
            'APIVersion'          => KuveytPosRequestDataMapper::API_VERSION,
            'MerchantId'          => $account->getClientId(),
            'UserName'            => $account->getUsername(),
            'CustomerId'          => $account->getCustomerId(),
            'HashData'            => 'shFFBwp4ZxLZXkHA+Z4jarwf09s=',
            'TransactionType'     => 'Sale',
            'TransactionSecurity' => 3,
            'InstallmentCount'    => $this->order['installment'],
            'Amount'              => 1001,
            'DisplayAmount'       => 1001,
            'CurrencyCode'        => '0949',
            'MerchantOrderId'     => $this->order['id'],
            'OkUrl'               => $this->order['success_url'],
            'FailUrl'             => $this->order['fail_url'],
        ];
        $inputs['CardHolderName']      = $card->getHolderName();
        $inputs['CardType']            = 'Visa';
        $inputs['CardNumber']          = $card->getNumber();
        $inputs['CardExpireDateYear']  = '25';
        $inputs['CardExpireDateMonth'] = '01';
        $inputs['CardCVV2']            = $card->getCvv();

        $result = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY, $card);
        $this->assertEquals($inputs, $result);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected)
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected)
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected)
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(KuveytPosAccount $account, array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($account, $order, $txType, $responseData);

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
                'trans_id'        => '298433',
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
                    'APIVersion'                       => '1.0.0',
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
                'trans_id'        => '298433',
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
                    'APIVersion'                       => '1.0.0',
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
        $endDate = new \DateTime('2023-07-08T22:44:31');
        yield [
            'order'    => [
                'id'       => '2023070849CD',
                'currency' => PosInterface::CURRENCY_TRY,
                'start_date' => $startDate,
                'end_date' => $endDate,
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
                    'APIVersion'                       => '1.0.0',
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
                'txType'       => PosInterface::TX_TYPE_PAY,
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
                    'APIVersion'                   => '1.0.0',
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
}
