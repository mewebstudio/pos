<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\TestUtil\TestUtilTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class KuveytSoapApiPosRequestDataMapperTest extends TestCase
{
    use TestUtilTrait;

    private KuveytPosAccount $account;

    private KuveytSoapApiPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    private KuveytPosRequestValueFormatter $valueFormatter;

    private KuveytPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->crypt          = $this->createMock(CryptInterface::class);
        $this->valueFormatter = new KuveytPosRequestValueFormatter();
        $this->valueMapper    = new KuveytPosRequestValueMapper();

        $this->requestDataMapper = new KuveytSoapApiPosRequestDataMapper(
            $this->valueMapper,
            $this->valueFormatter,
            $this->dispatcher,
            $this->crypt,
        );
    }

    public function testSupports(): void
    {
        $result = $this->requestDataMapper::supports(KuveytSoapApiPos::class);
        $this->assertTrue($result);

        $result = $this->requestDataMapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn('request-hash');

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        self::recursiveKsort($actual);
        self::recursiveKsort($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn('request-hash');

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        self::recursiveKsort($actual);
        self::recursiveKsort($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn('request-hash');

        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        self::recursiveKsort($actual);
        self::recursiveKsort($expected);

        $this->assertSame($expected, $actual);
    }

    public function testGet3DFormData(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;

        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);

        $this->requestDataMapper->create3DFormData(
            $this->account,
            ['id' => '123'],
            $paymentModel,
            $txType,
            'https://bank-gateway.com',
        );
    }

    public function testCreate3DPaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);

        $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
    }

    public function testCreateNonSecurePaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->createMock(CreditCardInterface::class)
        );
    }

    public function testCreateNonSecurePostAuthPaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, []);
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

    public function testCreateCustomQueryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createCustomQueryRequestData($this->account, []);
    }


    public static function createCancelRequestDataProvider(): \Generator
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
                'SaleReversal' => [
                    'request' => [
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
                        'VPosMessage'           => [
                            'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                            'InstallmentMaturityCommisionFlag' => 0,
                            'HashData'                         => 'request-hash',
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
                ],
            ],
        ];
    }

    public static function createRefundRequestDataProvider(): Generator
    {
        yield [
            'full_refund' => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'transaction_id'  => '298433',
                'amount'          => 1.01,
                'currency'        => PosInterface::CURRENCY_TRY,
            ],
            'tx_type'     => PosInterface::TX_TYPE_REFUND,
            'expected'    => [
                'DrawBack' => [
                    'request' => [
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
                        'VPosMessage'           => [
                            'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                            'InstallmentMaturityCommisionFlag' => 0,
                            'HashData'                         => 'request-hash',
                            'MerchantId'                       => '80',
                            'SubMerchantId'                    => 0,
                            'CustomerId'                       => '400235',
                            'UserName'                         => 'apiuser',
                            'CardType'                         => 'Visa',
                            'BatchID'                          => 0,
                            'TransactionType'                  => 'DrawBack',
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
                ],
            ],
        ];

        yield [
            'partial_refund' => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'transaction_id'  => '298433',
                'amount'          => 9.01,
                'order_amount'    => 10.01,
                'currency'        => PosInterface::CURRENCY_TRY,
            ],
            'tx_type'        => PosInterface::TX_TYPE_REFUND_PARTIAL,
            'expected'       => [
                'PartialDrawback' => [
                    'request' => [
                        'IsFromExternalNetwork' => true,
                        'BusinessKey'           => 0,
                        'ResourceId'            => 0,
                        'ActionId'              => 0,
                        'LanguageId'            => 0,
                        'CustomerId'            => '400235',
                        'MailOrTelephoneOrder'  => true,
                        'Amount'                => 901,
                        'MerchantId'            => '80',
                        'OrderId'               => '114293600',
                        'RRN'                   => '318923298433',
                        'Stan'                  => '298433',
                        'ProvisionNumber'       => '241839',
                        'VPosMessage'           => [
                            'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                            'InstallmentMaturityCommisionFlag' => 0,
                            'HashData'                         => 'request-hash',
                            'MerchantId'                       => '80',
                            'SubMerchantId'                    => 0,
                            'CustomerId'                       => '400235',
                            'UserName'                         => 'apiuser',
                            'CardType'                         => 'Visa',
                            'BatchID'                          => 0,
                            'TransactionType'                  => 'PartialDrawback',
                            'InstallmentCount'                 => 0,
                            'Amount'                           => 901,
                            'DisplayAmount'                    => 0,
                            'CancelAmount'                     => 901,
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
                ],
            ],
        ];
    }

    public static function createStatusRequestDataProvider(): Generator
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
                'GetMerchantOrderDetail' => [
                    'request' => [
                        'IsFromExternalNetwork' => true,
                        'BusinessKey'           => 0,
                        'ResourceId'            => 0,
                        'ActionId'              => 0,
                        'LanguageId'            => 0,
                        'CustomerId'            => '400235',
                        'MailOrTelephoneOrder'  => true,
                        'Amount'                => 0,
                        'MerchantId'            => '80',
                        'OrderId'               => 0,
                        'TransactionType'       => 0,
                        'VPosMessage'           => [
                            'APIVersion'                       => KuveytPosRequestDataMapper::API_VERSION,
                            'InstallmentMaturityCommisionFlag' => 0,
                            'HashData'                         => 'request-hash',
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
                ],
            ],
        ];
    }
}
