<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class KuveytPosResponseDataMapperTest extends TestCase
{
    private KuveytPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new KuveytPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );
        $this->responseDataMapper = new KuveytPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["HashDataError", false]
     * ["00", true]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"ResponseCode": "00"}, "00"]
     *
     */
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $actual = $this->responseDataMapper->extractMdStatus($responseData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testFormatAmount(): void
    {
        $class  = new \ReflectionObject($this->responseDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame(0.1, $method->invokeArgs($this->responseDataMapper, [10]));
        $this->assertSame(1.01, $method->invokeArgs($this->responseDataMapper, [101]));
    }

    /**
     * @dataProvider paymentTestDataProvider
     */
    public function testMapPaymentResponse(string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, []);
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);
        if ([] !== $responseData) {
            $this->assertArrayHasKey('all', $actualData);
            $this->assertIsArray($actualData['all']);
            $this->assertNotEmpty($actualData['all']);
        }

        unset($actualData['all']);
        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundTestDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        ksort($actualData);
        ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelTestDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        ksort($actualData);
        ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        $this->assertEquals($expectedData['capture_time'], $actualData['capture_time']);
        $this->assertEquals($expectedData['refund_time'], $actualData['refund_time']);
        $this->assertEquals($expectedData['cancel_time'], $actualData['cancel_time']);
        unset($actualData['transaction_time'], $expectedData['transaction_time']);
        unset($actualData['capture_time'], $expectedData['capture_time']);
        unset($actualData['refund_time'], $expectedData['refund_time']);
        unset($actualData['cancel_time'], $expectedData['cancel_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order
        );
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        if ([] !== $paymentResponse) {
            $this->assertIsArray($actualData['all']);
            $this->assertNotEmpty($actualData['all']);
        }

        $this->assertArrayHasKey('3d_all', $actualData);
        $this->assertIsArray($actualData['3d_all']);
        $this->assertNotEmpty($actualData['3d_all']);
        unset($actualData['all'], $actualData['3d_all']);

        \ksort($actualData);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DPayResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPayResponseData([], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMap3DHostResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DHostResponseData([], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
    }

    public function testMapOrderHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
    }

    public static function paymentTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                'IsEnrolled'      => 'true',
                'IsVirtual'       => 'false',
                'ResponseCode'    => 'MetaDataNotFound',
                'ResponseMessage' => 'Ödeme detayı bulunamadı.',
                'OrderId'         => '0',
                'TransactionTime' => '0001-01-01T00:00:00',
                'BusinessKey'     => '0',
            ],
            'expectedData' => [
                'order_id'          => null,
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => 'regular',
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => 'MetaDataNotFound',
                'status'            => 'declined',
                'status_detail'     => 'MetaDataNotFound',
                'error_code'        => 'MetaDataNotFound',
                'error_message'     => 'Ödeme detayı bulunamadı.',
                'installment_count' => null,
            ],
        ];
        yield 'empty' => [
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [],
            'expectedData' => [
                'order_id'          => null,
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => 'regular',
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => null,
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
            ],
        ];
        yield 'success1' => [
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'VPosMessage'     => [
                    'OrderId'             => '4480',
                    'OkUrl'               => 'http://localhost:10398//ThreeDModel/SuccessXml',
                    'FailUrl'             => 'http://localhost:10398//ThreeDModel/FailXml',
                    'MerchantId'          => '80',
                    'SubMerchantId'       => '0',
                    'CustomerId'          => '400235',
                    'HashPassword'        => 'c77dFssAnYSy6O2MJo+5tMYtGVc=',
                    'CardNumber'          => '5124********1609',
                    'BatchID'             => '1906',
                    'InstallmentCount'    => '0',
                    'Amount'              => '100',
                    'MerchantOrderId'     => '660723214',
                    'FECAmount'           => '0',
                    'CurrencyCode'        => '949',
                    'QeryId'              => '0',
                    'DebtId'              => '0',
                    'SurchargeAmount'     => '0',
                    'SGKDebtAmount'       => '0',
                    'TransactionSecurity' => '0',
                ],
                'IsEnrolled'      => 'true',
                'ProvisionNumber' => '896626',
                'RRN'             => '904115005554',
                'Stan'            => '005554',
                'ResponseCode'    => '00',
                'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                'OrderId'         => '4480',
                'TransactionTime' => '0001-01-01T00:00:00',
                'MerchantOrderId' => '660723214',
                'HashData'        => 'I7H/6nwfydM6VcwXsl82mqeC83o=',
            ],
            'expectedData' => [
                'order_id'          => '660723214',
                'transaction_id'    => '005554',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(),
                'currency'          => 'TRY',
                'amount'            => 1.0,
                'payment_model'     => 'regular',
                'auth_code'         => '896626',
                'ref_ret_num'       => '904115005554',
                'batch_num'         => '1906',
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'remote_order_id'   => '4480',
                'masked_number'     => '5124********1609',
                'installment_count' => 0,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'invalid_configuration'          => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'IsEnrolled'      => false,
                    'IsVirtual'       => false,
                    'ResponseCode'    => 'InvalidTransactionSecurity',
                    'ResponseMessage' => 'İşlem türü geçersizdir.',
                    'OrderId'         => 0,
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'ReferenceId'     => '2860e2d55e92435fa232a5dde55f68a9',
                    'MerchantId'      => [
                        '@xsi:nil' => true,
                        '#'        => '',
                    ],
                    'BusinessKey'     => 0,
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => null,
                    'currency'             => null,
                    'tx_status'            => null,
                    'md_error_message'     => 'İşlem türü geçersizdir.',
                    'masked_number'        => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_message'        => null,
                    'order_id'             => null,
                    'proc_return_code'     => 'InvalidTransactionSecurity',
                    'status'               => 'declined',
                    'status_detail'        => 'InvalidTransactionSecurity',
                    'error_code'           => 'InvalidTransactionSecurity',
                    'payment_model'        => null,
                    'installment_count'    => null,
                ],
            ],
            '3d_auth_success_payment_fail_1' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                    'VPosMessage'     => [
                        'OrderId'                          => '86483278',
                        'OkUrl'                            => 'https://www.example.com/testodeme',
                        'FailUrl'                          => 'https://www.example.com/testodeme',
                        'MerchantId'                       => '48544',
                        'SubMerchantId'                    => '0',
                        'CustomerId'                       => '123456',
                        'UserName'                         => 'fapapi',
                        'HashPassword'                     => 'Hiorgg24rNeRdHUvMCg//mOJn4U=',
                        'CardNumber'                       => '5124********1609',
                        'BatchID'                          => '1576',
                        'InstallmentCount'                 => '0',
                        'Amount'                           => '10',
                        'CancelAmount'                     => '0',
                        'MerchantOrderId'                  => 'MP-15',
                        'FECAmount'                        => '0',
                        'CurrencyCode'                     => '949',
                        'QeryId'                           => '0',
                        'DebtId'                           => '0',
                        'SurchargeAmount'                  => '0',
                        'SGKDebtAmount'                    => '0',
                        'TransactionSecurity'              => '3',
                        'DeferringCount'                   => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'InstallmentMaturityCommisionFlag' => '0',
                        'PaymentId'                        => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'OrderPOSTransactionId'            => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TranDate'                         => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TransactionUserId'                => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                    ],
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'OrderId'         => '86483278',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => 'MP-15',
                    'HashData'        => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
                    'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                    'BusinessKey'     => '202208456498416947',
                ],
                'paymentData'        => [
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => 'MetaDataNotFound',
                    'ResponseMessage' => 'Ödeme detayı bulunamadı.',
                    'OrderId'         => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'BusinessKey'     => '0',
                ],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => 0.1,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => '5124********1609',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => '1576',
                    'error_message'        => 'Ödeme detayı bulunamadı.',
                    'order_id'             => 'MP-15',
                    'proc_return_code'     => 'MetaDataNotFound',
                    'status'               => 'declined',
                    'status_detail'        => 'MetaDataNotFound',
                    'error_code'           => 'MetaDataNotFound',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            '3d_auth_success_payment_fail_2' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'VPosMessage'          => [
                        'APIVersion'          => '1.0.0',
                        'OkUrl'               => 'http://localhost:44785/Home/Success',
                        'FailUrl'             => 'http://localhost:44785/Home/Fail',
                        'HashData'            => 'lYJYMi/gVO9MWr32Pshaa/zAbSHY=',
                        'MerchantId'          => '80',
                        'SubMerchantId'       => '0',
                        'CustomerId'          => '400235',
                        'UserName'            => 'apiuser',
                        'CardNumber'          => '5124********1609',
                        'CardHolderName'      => 'afafa',
                        'CardType'            => 'MasterCard',
                        'BatchID'             => '0',
                        'TransactionType'     => 'Sale',
                        'InstallmentCount'    => '0',
                        'Amount'              => '100',
                        'DisplayAmount'       => '100',
                        'MerchantOrderId'     => 'Order 123',
                        'FECAmount'           => '0',
                        'CurrencyCode'        => '0949',
                        'QeryId'              => '0',
                        'DebtId'              => '0',
                        'SurchargeAmount'     => '0',
                        'SGKDebtAmount'       => '0',
                        'TransactionSecurity' => '3',
                        'TransactionSide'     => 'Auto',
                        'EntryGateMethod'     => 'VPOS_ThreeDModelPayGate',
                    ],
                    'IsEnrolled'           => 'true',
                    'IsVirtual'            => 'false',
                    'OrderId'              => '0',
                    'TransactionTime'      => '0001-01-01T00:00:00',
                    'ResponseCode'         => '00',
                    'ResponseMessage'      => 'HATATA',
                    'MD'                   => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'AuthenticationPacket' => 'WYGDgSIrSHDtYwF/WEN+nfwX63sppA=',
                    'ACSURL'               => 'https://acs.bkm.com.tr/mdpayacs/pareq',
                ],
                'paymentData'        => [
                    'IsEnrolled'      => 'false',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => 'EmptyMDException',
                    'ResponseMessage' => 'Geçerli bir MD değeri giriniz.',
                    'OrderId'         => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'BusinessKey'     => '0',
                ],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => 1.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => '5124********1609',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_message'        => 'Geçerli bir MD değeri giriniz.',
                    'order_id'             => 'Order 123',
                    'proc_return_code'     => 'EmptyMDException',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'error_code'           => 'EmptyMDException',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            '3d_auth_fail'                   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => 'HashDataError',
                    'ResponseMessage' => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                    'OrderId'         => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => '2020110828BC',
                    'ReferenceId'     => '9b8e2326a9df44c2b2aac0b98b11f0a4',
                    'BusinessKey'     => '0',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id'             => '2020110828BC',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => 'HashDataError',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'error_code'           => 'HashDataError',
                    'error_message'        => null,
                    'md_status'            => null,
                    'amount'               => null,
                    'currency'             => null,
                    'tx_status'            => null,
                    'masked_number'        => null,
                    'md_error_message'     => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                    'payment_model'        => null,
                    'installment_count'    => null,
                ],
            ],
            'success1'                       => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'VPosMessage'          => [
                        'APIVersion'          => '1.0.0',
                        'OkUrl'               => 'http://localhost:44785/Home/Success',
                        'FailUrl'             => 'http://localhost:44785/Home/Fail',
                        'HashData'            => 'lYJYMi/gVO9MWr32Pshaa/zAbSHY=',
                        'MerchantId'          => '80',
                        'SubMerchantId'       => '0',
                        'CustomerId'          => '400235',
                        'UserName'            => 'apiuser',
                        'CardNumber'          => '5124********1609',
                        'CardHolderName'      => 'afafa',
                        'CardType'            => 'MasterCard',
                        'BatchID'             => '0',
                        'TransactionType'     => 'Sale',
                        'InstallmentCount'    => '0',
                        'Amount'              => '100',
                        'DisplayAmount'       => '100',
                        'MerchantOrderId'     => 'Order 123',
                        'FECAmount'           => '0',
                        'CurrencyCode'        => '0949',
                        'QeryId'              => '0',
                        'DebtId'              => '0',
                        'SurchargeAmount'     => '0',
                        'SGKDebtAmount'       => '0',
                        'TransactionSecurity' => '3',
                        'TransactionSide'     => 'Auto',
                        'EntryGateMethod'     => 'VPOS_ThreeDModelPayGate',
                    ],
                    'IsEnrolled'           => 'true',
                    'IsVirtual'            => 'false',
                    'OrderId'              => '0',
                    'TransactionTime'      => '0001-01-01T00:00:00',
                    'ResponseCode'         => '00',
                    'ResponseMessage'      => 'HATATA',
                    'MD'                   => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'AuthenticationPacket' => 'WYGDgSIrSHDtYwF/WEN+nfwX63sppA=',
                    'ACSURL'               => 'https://acs.bkm.com.tr/mdpayacs/pareq',
                ],
                'paymentData'        => [
                    'VPosMessage'     => [
                        'OrderId'             => '4480',
                        'OkUrl'               => 'http://localhost:10398//ThreeDModel/SuccessXml',
                        'FailUrl'             => 'http://localhost:10398//ThreeDModel/FailXml',
                        'MerchantId'          => '80',
                        'SubMerchantId'       => '0',
                        'CustomerId'          => '400235',
                        'HashPassword'        => 'c77dFssAnYSy6O2MJo+5tMYtGVc=',
                        'CardNumber'          => '5124********1609',
                        'BatchID'             => '1906',
                        'InstallmentCount'    => '0',
                        'Amount'              => '100',
                        'MerchantOrderId'     => '660723214',
                        'FECAmount'           => '0',
                        'CurrencyCode'        => '949',
                        'QeryId'              => '0',
                        'DebtId'              => '0',
                        'SurchargeAmount'     => '0',
                        'SGKDebtAmount'       => '0',
                        'TransactionSecurity' => '0',
                    ],
                    'IsEnrolled'      => 'true',
                    'ProvisionNumber' => '896626',
                    'RRN'             => '904115005554',
                    'Stan'            => '005554',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                    'OrderId'         => '4480',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => '660723214',
                    'HashData'        => 'I7H/6nwfydM6VcwXsl82mqeC83o=',
                ],
                'expectedData'       => [
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'transaction_id'       => '005554',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'auth_code'            => '896626',
                    'ref_ret_num'          => '904115005554',
                    'batch_num'            => '1906',
                    'error_message'        => null,
                    'remote_order_id'      => '4480',
                    'order_id'             => '660723214',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 1.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'error_code'           => null,
                    'masked_number'        => '5124********1609',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
            'success_tdv2'                   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'VPosMessage'     => [
                        'OrderId'                          => '155767806',
                        'OkUrl'                            => 'http://localhost/kuveytpos/3d/response.php',
                        'FailUrl'                          => 'http://localhost/kuveytpos/3d/response.php',
                        'MerchantId'                       => '496',
                        'SubMerchantId'                    => '0',
                        'CustomerId'                       => '400235',
                        'UserName'                         => '',
                        'HashPassword'                     => '',
                        'CardNumber'                       => '51889619****2544',
                        'IsTemporaryCard'                  => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'BatchID'                          => '545',
                        'InstallmentCount'                 => '0',
                        'Amount'                           => '101',
                        'CancelAmount'                     => '0',
                        'MerchantOrderId'                  => '2024042111A0',
                        'OrderStatus'                      => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'RetryCount'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'FECAmount'                        => '0',
                        'CurrencyCode'                     => '949',
                        'QeryId'                           => '0',
                        'DebtId'                           => '0',
                        'SurchargeAmount'                  => '0',
                        'SGKDebtAmount'                    => '0',
                        'TransactionSecurity'              => '3',
                        'DeferringCount'                   => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'InstallmentMaturityCommisionFlag' => '0',
                        'PaymentId'                        => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'OrderPOSTransactionId'            => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TranDate'                         => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TransactionUserId'                => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'DeviceData'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'CardHolderData'                   => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                    ],
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'OrderId'         => '155767806',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => '2024042111A0',
                    'HashData'        => 'dlO20ZLqs4W5FfgY0VmUSBUpwoM=',
                    'MD'              => 'KdipxuB/+AncDNdIKzh5uRh4flnYqSp3drh/2yzaOQraHWjGF+NOkvSjYzbQtRQn',
                    'ReferenceId'     => '1d2a9d4cc853468090d915943341ad89',
                    'MerchantId'      => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'BusinessKey'     => '202404219999000000009059964',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'paymentData'        => [
                    'VPosMessage'     => [
                        'OrderId'                          => '155767806',
                        'OkUrl'                            => 'http://localhost/kuveytpos/3d/response.php',
                        'FailUrl'                          => 'http://localhost/kuveytpos/3d/response.php',
                        'MerchantId'                       => '496',
                        'SubMerchantId'                    => '0',
                        'CustomerId'                       => '400235',
                        'UserName'                         => '',
                        'HashPassword'                     => '',
                        'CardNumber'                       => '51889619****2544',
                        'IsTemporaryCard'                  => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'BatchID'                          => '545',
                        'InstallmentCount'                 => '0',
                        'Amount'                           => '101',
                        'CancelAmount'                     => '0',
                        'MerchantOrderId'                  => '2024042111A0',
                        'OrderStatus'                      => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'RetryCount'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'FECAmount'                        => '0',
                        'CurrencyCode'                     => '949',
                        'QeryId'                           => '0',
                        'DebtId'                           => '0',
                        'SurchargeAmount'                  => '0',
                        'SGKDebtAmount'                    => '0',
                        'TransactionSecurity'              => '3',
                        'DeferringCount'                   => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'InstallmentMaturityCommisionFlag' => '0',
                        'PaymentId'                        => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'OrderPOSTransactionId'            => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TranDate'                         => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TransactionUserId'                => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'DeviceData'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'CardHolderData'                   => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                    ],
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ProvisionNumber' => '050560',
                    'RRN'             => '411219539222',
                    'Stan'            => '539222',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                    'OrderId'         => '155767806',
                    'TransactionTime' => '2024-04-21T19:01:28.95',
                    'MerchantOrderId' => '2024042111A0',
                    'HashData'        => 'N7zs7LHEy3lx6N2nb0FUG2UcnUw=',
                    'MerchantId'      => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'BusinessKey'     => '202404219999000000009042173',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expectedData'       => [
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'transaction_id'       => '539222',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'auth_code'            => '050560',
                    'ref_ret_num'          => '411219539222',
                    'batch_num'            => '545',
                    'error_message'        => null,
                    'remote_order_id'      => '155767806',
                    'order_id'             => '2024042111A0',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'error_code'           => null,
                    'masked_number'        => '51889619****2544',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
        ];
    }

    public static function statusTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [],
                ],
            ],
            'expectedData' => [
                'order_id'          => null,
                'auth_code'         => null,
                'proc_return_code'  => null,
                'transaction_id'    => null,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'order_status'      => null,
                'transaction_type'  => null,
                'masked_number'     => null,
                'first_amount'      => null,
                'capture_amount'    => null,
                'status'            => 'declined',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => null,
                'capture_time'      => null,
                'transaction_time'  => null,
                'currency'          => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];
        yield 'success1' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'OrderContract' => [
                            'IsSelected'          => false,
                            'IsSelectable'        => true,
                            'OrderId'             => 114_293_600,
                            'MerchantOrderId'     => '2023070849CD',
                            'MerchantId'          => 496,
                            'CardHolderName'      => 'John Doe',
                            'CardType'            => 'MasterCard',
                            'CardNumber'          => '518896******2544',
                            'OrderDate'           => '2023-07-08T23:45:15.797',
                            'OrderStatus'         => 1,
                            'LastOrderStatus'     => 1,
                            'OrderType'           => 1,
                            'TransactionStatus'   => 1,
                            'FirstAmount'         => '1.01',
                            'CancelAmount'        => '0.00',
                            'DrawbackAmount'      => '0.00',
                            'ClosedAmount'        => '0.00',
                            'FEC'                 => '0949',
                            'VPSEntryMode'        => 'ECOM',
                            'InstallmentCount'    => 0,
                            'TransactionSecurity' => 3,
                            'ResponseCode'        => '00',
                            'ResponseExplain'     => 'İşlem gerçekleştirildi.',
                            'EndOfDayStatus'      => 2,
                            'TransactionSide'     => 'Auto',
                            'CardHolderIPAddress' => '',
                            'MerchantIPAddress'   => '92.38.180.58',
                            'MerchantUserName'    => 'apitest',
                            'ProvNumber'          => '241839',
                            'BatchId'             => 491,
                            'CardExpireDate'      => '2506',
                            'PosTerminalId'       => 'VP008759',
                            'Explain'             => '',
                            'Explain2'            => '',
                            'Explain3'            => '',
                            'RRN'                 => '318923298433',
                            'Stan'                => '298433',
                            'UserName'            => 'vposuser',
                            'HostName'            => 'STD8BOATEST2',
                            'SystemDate'          => '2023-07-08T23:45:15.8',
                            'UpdateUserName'      => 'vposuser',
                            'UpdateHostName'      => 'STD8BOATEST2',
                            'UpdateSystemDate'    => '2023-07-08T23:45:35.283',
                            'EndOfDayDate'        => null,
                            'HostIP'              => '172.20.8.85',
                            'FECAmount'           => '0',
                            'IdentityTaxNumber'   => '',
                            'QueryId'             => '0',
                            'DebtId'              => '0',
                            'DebtorName'          => '',
                            'Period'              => '',
                            'SurchargeAmount'     => '0',
                            'SGKDebtAmount'       => '0',
                            'DeferringCount'      => null,
                        ],
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'          => '2023070849CD',
                'auth_code'         => '241839',
                'proc_return_code'  => '00',
                'transaction_id'    => '298433',
                'ref_ret_num'       => '318923298433',
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => null,
                'masked_number'     => '518896******2544',
                'first_amount'      => 1.01,
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'status_detail'     => null,
                'capture'           => true,
                'remote_order_id'   => '114293600',
                'currency'          => PosInterface::CURRENCY_TRY,
                'capture_time'      => new \DateTimeImmutable('2023-07-08T23:45:35.283'),
                'transaction_time'  => new \DateTimeImmutable('2023-07-08T23:45:15.797'),
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];
        yield 'tdv2_fail_hash_error' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [
                        'Result' => [
                            'ErrorMessage' => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                            'ErrorCode'    => 'HashDataError',
                            'IsFriendly'   => true,
                            'Severity'     => 'BusinessError',
                        ],
                    ],
                    'Success' => false,
                    'Value'   => [
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'         => null,
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => null,
                'error_code'        => 'HashDataError',
                'error_message'     => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                'first_amount'      => null,
                'installment_count' => null,
                'masked_number'     => null,
                'order_id'          => null,
                'order_status'      => null,
                'proc_return_code'  => null,
                'ref_ret_num'       => null,
                'refund_amount'     => null,
                'status'            => 'declined',
                'status_detail'     => null,
                'transaction_id'    => null,
                'transaction_type'  => null,
                'transaction_time'  => null,
                'capture_time'      => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];
        yield 'tdv2_success_tx_pay' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'OrderContract' => [
                            'IsSelected'          => false,
                            'IsSelectable'        => true,
                            'OrderId'             => 155768281,
                            'MerchantOrderId'     => '20240424C7A5',
                            'MerchantId'          => 496,
                            'CardHolderName'      => 'John Doe',
                            'CardType'            => 'MasterCard',
                            'CardNumber'          => '518896******2544',
                            'OrderDate'           => '2024-04-24T16:03:42.07',
                            'OrderStatus'         => 1,
                            'LastOrderStatus'     => 1,
                            'OrderType'           => 1,
                            'TransactionStatus'   => 1,
                            'FirstAmount'         => '10.01',
                            'CancelAmount'        => '0.00',
                            'DrawbackAmount'      => '0.00',
                            'ClosedAmount'        => '0.00',
                            'FEC'                 => '0949',
                            'VPSEntryMode'        => 'ECOM',
                            'InstallmentCount'    => 0,
                            'TransactionSecurity' => 3,
                            'ResponseCode'        => '00',
                            'ResponseExplain'     => 'İşlem gerçekleştirildi.',
                            'EndOfDayStatus'      => 1,
                            'TransactionSide'     => 'Auto',
                            'CardHolderIPAddress' => '',
                            'MerchantIPAddress'   => '45.130.202.59',
                            'MerchantUserName'    => 'apitest',
                            'ProvNumber'          => '050990',
                            'BatchId'             => 545,
                            'CardExpireDate'      => '2506',
                            'PosTerminalId'       => 'VP008759',
                            'Explain'             => '',
                            'Explain2'            => '',
                            'Explain3'            => '',
                            'RRN'                 => '411516539768',
                            'Stan'                => '539768',
                            'UserName'            => 'vposuser',
                            'HostName'            => 'STD8BOATEST1',
                            'SystemDate'          => '2024-04-24T16:03:42.077',
                            'UpdateUserName'      => 'vposuser',
                            'UpdateHostName'      => 'STD8BOATEST2',
                            'UpdateSystemDate'    => '2024-04-24T16:04:12.373',
                            'EndOfDayDate'        => null,
                            'HostIP'              => '172.20.8.84',
                            'FECAmount'           => '0',
                            'IdentityTaxNumber'   => '',
                            'QueryId'             => '0',
                            'DebtId'              => '0',
                            'DebtorName'          => '',
                            'Period'              => '',
                            'SurchargeAmount'     => '0',
                            'SGKDebtAmount'       => '0',
                            'DeferringCount'      => null,
                        ],
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'         => '050990',
                'capture'           => true,
                'capture_amount'    => 10.01,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'first_amount'      => 10.01,
                'installment_count' => 0,
                'masked_number'     => '518896******2544',
                'order_id'          => '20240424C7A5',
                'order_status'      => 'PAYMENT_COMPLETED',
                'proc_return_code'  => '00',
                'ref_ret_num'       => '411516539768',
                'refund_amount'     => null,
                'remote_order_id'   => '155768281',
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '539768',
                'transaction_type'  => null,
                'transaction_time'  => new \DateTimeImmutable('2024-04-24T16:03:42.07'),
                'capture_time'      => new \DateTimeImmutable('2024-04-24T16:04:12.373'),
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];
        yield 'tdv2_success_tx_pay_then_cancel' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'OrderContract' => [
                            'IsSelected'          => false,
                            'IsSelectable'        => true,
                            'OrderId'             => 155768281,
                            'MerchantOrderId'     => '20240424C7A5',
                            'MerchantId'          => 496,
                            'CardHolderName'      => 'John Doe',
                            'CardType'            => 'MasterCard',
                            'CardNumber'          => '518896******2544',
                            'OrderDate'           => '2024-04-24T16:03:42.07',
                            'OrderStatus'         => 1,
                            'LastOrderStatus'     => 6,
                            'OrderType'           => 1,
                            'TransactionStatus'   => 1,
                            'FirstAmount'         => '10.01',
                            'CancelAmount'        => '10.01',
                            'DrawbackAmount'      => '0.00',
                            'ClosedAmount'        => '0.00',
                            'FEC'                 => '0949',
                            'VPSEntryMode'        => 'ECOM',
                            'InstallmentCount'    => 0,
                            'TransactionSecurity' => 3,
                            'ResponseCode'        => '00',
                            'ResponseExplain'     => 'İşlem gerçekleştirildi.',
                            'EndOfDayStatus'      => 1,
                            'TransactionSide'     => 'Auto',
                            'CardHolderIPAddress' => '',
                            'MerchantIPAddress'   => '45.130.202.59',
                            'MerchantUserName'    => 'apitest',
                            'ProvNumber'          => '050990',
                            'BatchId'             => 545,
                            'CardExpireDate'      => '2506',
                            'PosTerminalId'       => 'VP008759',
                            'Explain'             => '',
                            'Explain2'            => '',
                            'Explain3'            => '',
                            'RRN'                 => '411516539768',
                            'Stan'                => '539768',
                            'UserName'            => 'vposuser',
                            'HostName'            => 'STD8BOATEST1',
                            'SystemDate'          => '2024-04-24T16:03:42.077',
                            'UpdateUserName'      => 'webgate',
                            'UpdateHostName'      => 'STD8BOATEST1',
                            'UpdateSystemDate'    => '2024-04-24T16:09:27.067',
                            'EndOfDayDate'        => null,
                            'HostIP'              => '172.20.8.84',
                            'FECAmount'           => '0',
                            'IdentityTaxNumber'   => '',
                            'QueryId'             => '0',
                            'DebtId'              => '0',
                            'DebtorName'          => '',
                            'Period'              => '',
                            'SurchargeAmount'     => '0',
                            'SGKDebtAmount'       => '0',
                            'DeferringCount'      => null,
                        ],
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'         => '050990',
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'first_amount'      => 10.01,
                'installment_count' => 0,
                'masked_number'     => '518896******2544',
                'order_id'          => '20240424C7A5',
                'order_status'      => 'CANCELED',
                'proc_return_code'  => '00',
                'ref_ret_num'       => '411516539768',
                'refund_amount'     => null,
                'remote_order_id'   => '155768281',
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '539768',
                'transaction_type'  => null,
                'transaction_time'  => new \DateTimeImmutable('2024-04-24T16:03:42.07'),
                'capture_time'      => null,
                'refund_time'       => null,
                'cancel_time'       => new \DateTimeImmutable('2024-04-24T16:09:27.067'),
            ],
        ];
        yield 'tdv2_success_tx_pay_then_refund' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'OrderContract' => [
                            'IsSelected'          => false,
                            'IsSelectable'        => true,
                            'OrderId'             => 155768298,
                            'MerchantOrderId'     => '202404240DEE',
                            'MerchantId'          => 496,
                            'CardHolderName'      => 'John Doe',
                            'CardType'            => 'MasterCard',
                            'CardNumber'          => '518896******2544',
                            'OrderDate'           => '2024-04-24T16:33:44.01',
                            'OrderStatus'         => 1,
                            'LastOrderStatus'     => 4,
                            'OrderType'           => 1,
                            'TransactionStatus'   => 1,
                            'FirstAmount'         => '10.01',
                            'CancelAmount'        => '0.00',
                            'DrawbackAmount'      => '10.01',
                            'ClosedAmount'        => '0.00',
                            'FEC'                 => '0949',
                            'VPSEntryMode'        => 'ECOM',
                            'InstallmentCount'    => 0,
                            'TransactionSecurity' => 1,
                            'ResponseCode'        => '00',
                            'ResponseExplain'     => 'İşlem gerçekleştirildi.',
                            'EndOfDayStatus'      => 2,
                            'TransactionSide'     => 'Auto',
                            'CardHolderIPAddress' => '',
                            'MerchantIPAddress'   => '45.130.202.55',
                            'MerchantUserName'    => 'apitest',
                            'ProvNumber'          => '051004',
                            'BatchId'             => 545,
                            'CardExpireDate'      => '2506',
                            'PosTerminalId'       => 'VP008759',
                            'Explain'             => '',
                            'Explain2'            => '',
                            'Explain3'            => '',
                            'RRN'                 => '411516539788',
                            'Stan'                => '539788',
                            'UserName'            => 'vposuser',
                            'HostName'            => 'STD8BOATEST2',
                            'SystemDate'          => '2024-04-24T16:33:44.02',
                            'UpdateUserName'      => 'webgate',
                            'UpdateHostName'      => 'STD8BOATEST2',
                            'UpdateSystemDate'    => '2024-04-26T10:59:49.443',
                            'EndOfDayDate'        => '2024-04-24T17:08:46.15',
                            'HostIP'              => '172.20.8.85',
                            'FECAmount'           => '0',
                            'IdentityTaxNumber'   => '',
                            'QueryId'             => '0',
                            'DebtId'              => '0',
                            'DebtorName'          => '',
                            'Period'              => '',
                            'SurchargeAmount'     => '0',
                            'SGKDebtAmount'       => '0',
                            'DeferringCount'      => null,
                        ],
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'         => '051004',
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'first_amount'      => 10.01,
                'installment_count' => 0,
                'masked_number'     => '518896******2544',
                'order_id'          => '202404240DEE',
                'order_status'      => 'FULLY_REFUNDED',
                'proc_return_code'  => '00',
                'ref_ret_num'       => '411516539788',
                'refund_amount'     => null,
                'remote_order_id'   => '155768298',
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '539788',
                'transaction_type'  => null,
                'transaction_time'  => new \DateTimeImmutable('2024-04-24T16:33:44.01'),
                'capture_time'      => null,
                'refund_time'       => new \DateTimeImmutable('2024-04-26T10:59:49.443'),
                'cancel_time'       => null,
            ],
        ];
    }

    public static function cancelTestDataProvider(): iterable
    {
        yield 'success1' => [
            'responseData' => [
                'SaleReversalResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'ProvisionNumber' => '241839',
                        'RRN'             => '318923298433',
                        'Stan'            => '298433',
                        'ResponseCode'    => '00',
                        'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                        'OrderId'         => '114293600',
                        'TransactionTime' => '2023-07-08T23:45:15.797',
                        'MerchantOrderId' => '2023070849CD',
                        'CurrencyCode'    => '0949',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202208456498416947',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => '2023070849CD',
                'auth_code'        => '241839',
                'proc_return_code' => '00',
                'transaction_id'   => '298433',
                'currency'         => PosInterface::CURRENCY_TRY,
                'error_message'    => null,
                'ref_ret_num'      => '318923298433',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293600',
            ],
        ];
        yield 'fail1' => [
            'responseData' => [
                'SaleReversalResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'OrderId'         => 0,
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202307089999000000009015473',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => null,
                'status_detail'    => null,
            ],
        ];
        yield 'fail_already_cancelled' => [
            'responseData' => [
                'SaleReversalResult' => [
                    'Results' => [
                        'Result' => [
                            0 => [
                                'ErrorMessage' => 'İşlem daha önce iptal edilmiştir.',
                                'ErrorCode'    => '21',
                                'IsFriendly'   => null,
                                'Severity'     => 'Error',
                            ],
                            1 => [
                                'ErrorMessage' => 'İşleminizi şu an gerçekleştiremiyoruz, lütfen daha sonra tekrar deneyiniz.',
                                'ErrorCode'    => 'IntegrationFatalException',
                                'IsFriendly'   => null,
                                'Severity'     => 'Error',
                            ],
                        ],
                    ],
                    'Success' => null,
                    'Value'   => [
                        'IsEnrolled'      => null,
                        'IsVirtual'       => null,
                        'ResponseCode'    => 'DbLayerError',
                        'OrderId'         => 0,
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'MerchantId'      => null,
                        'BusinessKey'     => '0',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => 'DbLayerError',
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => 'İşlem daha önce iptal edilmiştir.',
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => '21',
                'status_detail'    => null,
            ],
        ];
        yield 'tdv2_fail_already_cancelled' => [
            'responseData' => [
                'SaleReversalResult' => [
                    'Results' => [
                        'Result' => [
                            'ErrorMessage' => 'İşlem daha önce iptal edilmiştir.',
                            'ErrorCode'    => '21',
                            'IsFriendly'   => true,
                            'Severity'     => 'BusinessError',
                        ],
                    ],
                    'Success' => false,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'ResponseCode'    => 'DbLayerError',
                        'OrderId'         => 0,
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'MerchantId'      => null,
                        'BusinessKey'     => '0',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => 'DbLayerError',
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => 'İşlem daha önce iptal edilmiştir.',
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => '21',
                'status_detail'    => null,
            ],
        ];
    }

    public static function refundTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
                'PartialDrawbackResult' => [
                    'Results' => [],
                    'Success' => null,
                    'Value'   => [
                        'IsEnrolled'      => null,
                        'IsVirtual'       => null,
                        'RRN'             => '319013298460',
                        'Stan'            => '298460',
                        'ResponseCode'    => '28',
                        'ResponseMessage' => 'İptal Edilen İşlem İade Yapılamaz',
                        'OrderId'         => 114_293_625,
                        'TransactionTime' => '2023-07-09T13:38:00.9396957',
                        'MerchantOrderId' => '202307093C2D',
                        'CurrencyCode'    => '0949',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202307099999000000003235752',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => '202307093C2D',
                'auth_code'        => null,
                'proc_return_code' => '28',
                'transaction_id'   => '298460',
                'currency'         => null,
                'error_message'    => 'İptal Edilen İşlem İade Yapılamaz',
                'ref_ret_num'      => '319013298460',
                'status'           => 'declined',
                'error_code'       => '28',
                'status_detail'    => null,
                'remote_order_id'  => '114293625',
            ],
        ];

        yield 'fail2' => [
            'responseData' => [
                'PartialDrawbackResult' => [
                    'Results' => [],
                    'Success' => null,
                    'Value'   => [
                        'IsEnrolled'      => null,
                        'IsVirtual'       => null,
                        'OrderId'         => 0,
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202307099999000000003252739',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => null,
                'status_detail'    => null,
            ],
        ];
        yield 'tdv2_fail_partial_refund_not_allowed' => [
            'responseData' => [
                'PartialDrawbackResult' => [
                    'Results' => [
                        'Result' => [
                            'ErrorMessage' => 'Kısmi iade işlemi, satışla aynı gün içerisindeyse tutarın tamamı için yapılamaz. Tutarın tamamı için iptal işlemi yapabilirsiniz.',
                            'IsFriendly'   => true,
                            'Severity'     => 'BusinessError',
                        ],
                    ],
                    'Success' => false,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'ResponseCode'    => 'DbLayerError',
                        'OrderId'         => 0,
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'MerchantId'      => null,
                        'BusinessKey'     => '0',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => 'DbLayerError',
                'transaction_id'   => null,
                'currency'         => null,
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => 'DbLayerError',
                'error_message'    => 'Kısmi iade işlemi, satışla aynı gün içerisindeyse tutarın tamamı için yapılamaz. Tutarın tamamı için iptal işlemi yapabilirsiniz.',
                'status_detail'    => null,
            ],
        ];

        yield 'success1' => [
            'responseData' => [
                'PartialDrawbackResult' => [
                    'Results' => [],
                    'Success' => null,
                    'Value'   => [
                        'IsEnrolled'      => null,
                        'IsVirtual'       => null,
                        'ProvisionNumber' => '241859',
                        'RRN'             => '319014298463',
                        'Stan'            => '298463',
                        'ResponseCode'    => '00',
                        'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                        'OrderId'         => 114_293_626,
                        'TransactionTime' => '2023-07-09T14:07:41.9306297',
                        'MerchantOrderId' => '202307091285',
                        'CurrencyCode'    => '0949',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202307099999000000003252996',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => '202307091285',
                'auth_code'        => '241859',
                'proc_return_code' => '00',
                'transaction_id'   => '298463',
                'currency'         => PosInterface::CURRENCY_TRY,
                'error_message'    => null,
                'ref_ret_num'      => '319014298463',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293626',
            ],
        ];

        yield 'tdv2_success_full_refund' => [
            'responseData' => [
                'DrawBackResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'ProvisionNumber' => '050823',
                        'RRN'             => '411415539590',
                        'Stan'            => '539590',
                        'ResponseCode'    => '00',
                        'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                        'OrderId'         => 155767855,
                        'TransactionTime' => '2024-04-23T15:19:15.7471578',
                        'MerchantOrderId' => '202404229EAC',
                        'CurrencyCode'    => '0949',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202404239999000000013631520',
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'        => '050823',
                'currency'         => 'TRY',
                'error_code'       => null,
                'error_message'    => null,
                'order_id'         => '202404229EAC',
                'proc_return_code' => '00',
                'ref_ret_num'      => '411415539590',
                'remote_order_id'  => '155767855',
                'status'           => 'approved',
                'status_detail'    => null,
                'transaction_id'   => '539590',
            ],
        ];
        yield 'tdv2_success1' => [
            'responseData' => [
                'PartialDrawbackResult' => [
                    'Results' => [

                    ],
                    'Success' => true,
                    'Value'   => [
                        'IsEnrolled'      => false,
                        'IsVirtual'       => false,
                        'ProvisionNumber' => '050569',
                        'RRN'             => '411220539231',
                        'Stan'            => '539231',
                        'ResponseCode'    => '00',
                        'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                        'OrderId'         => 155767811,
                        'TransactionTime' => '2024-04-21T20:09:21.3829986',
                        'MerchantOrderId' => '202404218A62',
                        'CurrencyCode'    => '0949',
                        'MerchantId'      => null,
                        'BusinessKey'     => '202404219999000000009542260',
                    ],
                ],
            ],
            'expectedData' => [
                'auth_code'        => '050569',
                'currency'         => 'TRY',
                'error_code'       => null,
                'error_message'    => null,
                'order_id'         => '202404218A62',
                'proc_return_code' => '00',
                'ref_ret_num'      => '411220539231',
                'remote_order_id'  => '155767811',
                'status'           => 'approved',
                'status_detail'    => null,
                'transaction_id'   => '539231',
            ],
        ];
    }
}
