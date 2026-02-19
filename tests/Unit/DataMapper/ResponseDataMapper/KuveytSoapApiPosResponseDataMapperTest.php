<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseDataMapper\KuveytSoapApiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\KuveytSoapApiPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class KuveytSoapApiPosResponseDataMapperTest extends TestCase
{
    private KuveytSoapApiPosResponseDataMapper $responseDataMapper;

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

        $this->responseDataMapper = new KuveytSoapApiPosResponseDataMapper(
            $this->responseValueFormatter,
            $this->responseValueMapper,
            $this->logger
        );
    }

    public function testSupports(): void
    {
        $result = $this->responseDataMapper::supports(KuveytSoapApiPos::class);
        $this->assertTrue($result);

        $result = $this->responseDataMapper::supports(AkbankPos::class);
        $this->assertFalse($result);
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

    public function testMapPaymentResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapPaymentResponse(
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
    }

    /**
     * @dataProvider refundTestDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $txType         = PosInterface::TX_TYPE_REFUND;
        $drawbackResult = $responseData['PartialDrawbackResponse']['PartialDrawbackResult'] ?? $responseData['DrawBackResponse']['DrawBackResult'];

        if ($expectedData['status'] === ResponseDataMapperInterface::TX_APPROVED) {
            $this->responseValueMapper->expects($this->once())
                ->method('mapCurrency')
                ->with($drawbackResult['Value']['CurrencyCode'], $txType)
                ->willReturn($expectedData['currency']);
        }

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
        $txType = PosInterface::TX_TYPE_CANCEL;
        if ($expectedData['status'] === ResponseDataMapperInterface::TX_APPROVED) {
            $this->responseValueMapper->expects($this->once())
                ->method('mapCurrency')
                ->with($responseData['SaleReversalResponse']['SaleReversalResult']['Value']['CurrencyCode'], $txType)
                ->willReturn($expectedData['currency']);
        }

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
        if ($expectedData['status'] === ResponseDataMapperInterface::TX_APPROVED) {
            $txType        = PosInterface::TX_TYPE_STATUS;
            $orderContract = $responseData['GetMerchantOrderDetailResponse']['GetMerchantOrderDetailResult']['Value']['OrderContract'];
            $this->responseValueMapper->expects($this->once())
                ->method('mapOrderStatus')
                ->with($orderContract['LastOrderStatus'])
                ->willReturn($expectedData['order_status']);

            $this->responseValueMapper->expects($this->once())
                ->method('mapCurrency')
                ->with($orderContract['FEC'], $txType)
                ->willReturn($expectedData['currency']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatAmount')
                ->with($orderContract['FirstAmount'], $txType)
                ->willReturn($expectedData['first_amount']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($orderContract['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);

            $dateTimeMatcher = $this->atLeastOnce();
            $this->responseValueFormatter->expects($dateTimeMatcher)
                ->method('formatDateTime')
                ->with($this->callback(function ($dateTime) use ($dateTimeMatcher, $orderContract): bool {
                    if ($dateTimeMatcher->getInvocationCount() === 1) {
                        return $dateTime === $orderContract['OrderDate'];
                    }

                    if ($dateTimeMatcher->getInvocationCount() === 2) {
                        return $dateTime === $orderContract['UpdateSystemDate'];
                    }

                    return false;
                }), $txType)
                ->willReturnCallback(
                    function () use ($dateTimeMatcher, $expectedData) {
                        if ($dateTimeMatcher->getInvocationCount() === 1) {
                            return $expectedData['transaction_time'];
                        }

                        if ($dateTimeMatcher->getInvocationCount() === 2) {
                            return $expectedData['capture_time'] ?? $expectedData['cancel_time'] ?? $expectedData['refund_time'];
                        }

                        return false;
                    }
                );
        }

        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DPaymentData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPaymentData(
            [],
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
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

    public static function statusTestDataProvider(): \Generator
    {
        yield 'fail1' => [
            'responseData' => [
                'GetMerchantOrderDetailResponse' => [
                    'GetMerchantOrderDetailResult' => [
                        'Results' => [],
                        'Success' => true,
                        'Value'   => [],
                    ],
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
                'GetMerchantOrderDetailResponse' => [
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
                'GetMerchantOrderDetailResponse' => [
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
                'GetMerchantOrderDetailResponse' => [
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
                'GetMerchantOrderDetailResponse' => [
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
                'GetMerchantOrderDetailResponse' => [
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

    public static function cancelTestDataProvider(): \Generator
    {
        yield 'success1' => [
            'responseData' => [
                'SaleReversalResponse' => [
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
                'SaleReversalResponse' => [
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
                'SaleReversalResponse' => [
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
                'SaleReversalResponse' => [
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

    public static function refundTestDataProvider(): \Generator
    {
        yield 'fail1' => [
            'responseData' => [
                'PartialDrawbackResponse' => [
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
                'PartialDrawbackResponse' => [
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
                'PartialDrawbackResponse' => [
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
                'PartialDrawbackResponse' => [
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
                'DrawBackResponse' => [
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
                'PartialDrawbackResponse' => [
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
