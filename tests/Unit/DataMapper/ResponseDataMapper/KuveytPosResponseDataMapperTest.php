<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    /** @var ResponseValueFormatterInterface & MockObject */
    private ResponseValueFormatterInterface $responseValueFormatter;

    /** @var ResponseValueMapperInterface & MockObject */
    private ResponseValueMapperInterface $responseValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->responseValueFormatter = $this->createMock(ResponseValueFormatterInterface::class);
        $this->responseValueMapper    = $this->createMock(ResponseValueMapperInterface::class);

        $this->responseDataMapper = new KuveytPosResponseDataMapper(
            $this->responseValueFormatter,
            $this->responseValueMapper,
            $this->logger
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
     * @dataProvider paymentTestDataProvider
     */
    public function testMapPaymentResponse(string $txType, array $responseData, array $expectedData): void
    {
        if (isset($responseData['VPosMessage'])) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatAmount')
                ->with($responseData['VPosMessage']['Amount'], $txType)
                ->willReturn($expectedData['amount']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($responseData['VPosMessage']['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);

            $this->responseValueMapper->expects($this->once())
                ->method('mapCurrency')
                ->with($responseData['VPosMessage']['CurrencyCode'], $txType)
                ->willReturn($expectedData['currency']);

            if (isset($expectedData['transaction_time'])) {
                $this->responseValueFormatter->expects($this->once())
                    ->method('formatDateTime')
                    ->with('now', $txType)
                    ->willReturn($expectedData['transaction_time']);
            }
        }

        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, []);

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

    public function testMapRefundResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapRefundResponse([]);
    }

    public function testMapCancelResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapCancelResponse([]);
    }

    public function testMapStatusResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapStatusResponse([]);
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $paymentResponse, array $expectedData): void
    {
        if (isset($threeDResponseData['VPosMessage']['TransactionType'])) {
            $this->responseValueMapper->expects($this->once())
                ->method('mapTxType')
                ->with($threeDResponseData['VPosMessage']['TransactionType'])
                ->willReturn($expectedData['transaction_type']);
        }

        if ($threeDResponseData['ResponseCode'] === '00') {
            $this->responseValueMapper->expects($this->once())
                ->method('mapSecureType')
                ->with($threeDResponseData['VPosMessage']['TransactionSecurity'], $txType)
                ->willReturn($expectedData['payment_model']);

            $amountMatcher = $this->atLeastOnce();
            $this->responseValueFormatter->expects($amountMatcher)
                ->method('formatAmount')
                ->with($this->callback(function ($amount) use ($amountMatcher, $threeDResponseData, $paymentResponse): bool {
                    if ($amountMatcher->getInvocationCount() === 1) {
                        return $amount === $threeDResponseData['VPosMessage']['Amount'];
                    }

                    if ($amountMatcher->getInvocationCount() === 2) {
                        return $amount === $paymentResponse['VPosMessage']['Amount'];
                    }

                    return false;
                }), $txType)
                ->willReturnCallback(
                    fn () => $expectedData['amount']
                );

            $currencyMatcher = $this->atLeastOnce();
            $this->responseValueMapper->expects($currencyMatcher)
                ->method('mapCurrency')
                ->with($this->callback(function ($amount) use ($currencyMatcher, $threeDResponseData, $paymentResponse): bool {
                    if ($currencyMatcher->getInvocationCount() === 1) {
                        return $amount === $threeDResponseData['VPosMessage']['CurrencyCode'];
                    }

                    if ($currencyMatcher->getInvocationCount() === 2) {
                        return $amount === $paymentResponse['VPosMessage']['CurrencyCode'];
                    }

                    return false;
                }), $txType)
                ->willReturnCallback(
                    fn () => $expectedData['currency']
                );

            if ($expectedData['status'] === ResponseDataMapperInterface::TX_APPROVED) {
                $this->responseValueFormatter->expects($this->once())
                    ->method('formatInstallment')
                    ->with($paymentResponse['VPosMessage']['InstallmentCount'], $txType)
                    ->willReturn($expectedData['installment_count']);
            }
        }

        if (isset($expectedData['transaction_time'])) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatDateTime')
                ->with('now', $txType)
                ->willReturn($expectedData['transaction_time']);
        }

        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order
        );

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
}
