<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class ToslaPosResponseDataMapperTest extends TestCase
{
    private ToslaPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new ToslaPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new ToslaPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["2", false]
     * ["3", false]
     * ["4", false]
     * ["7", false]
     * ["1", true]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"MdStatus": "1"}, "1"]
     *
     */
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $actual = $this->responseDataMapper->extractMdStatus($responseData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider paymentDataProvider
     */
    public function testMapPaymentResponse(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, $order);
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }


    /**
     * @dataProvider threeDPayPaymentDataProvider
     */
    public function testMap3DPayResponseData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($responseData, $txType, $order);
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDHostPaymentDataProvider
     */
    public function testMap3DHostResponseData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DHostResponseData($responseData, $txType, $order);
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider statusResponseDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        if (isset($responseData['CreateDate'])) {
            $this->assertSame($actualData['transaction_time']->format('YmdHis'), $responseData['CreateDate']);
            $this->assertEquals($expectedData['capture_time'], $actualData['capture_time']);
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
            $this->assertEquals($expectedData['refund_time'], $actualData['refund_time']);
            $this->assertEquals($expectedData['cancel_time'], $actualData['cancel_time']);
            unset($actualData['transaction_time'], $expectedData['transaction_time']);
            unset($actualData['capture_time'], $expectedData['capture_time']);
            unset($actualData['refund_time'], $expectedData['refund_time']);
            unset($actualData['cancel_time'], $expectedData['cancel_time']);
        }

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($actualData);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider orderHistoryDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapOrderHistoryResponse($responseData);
        if (isset($responseData['Transactions'])) {
            $this->assertCount($actualData['trans_count'], $actualData['transactions']);
            if (count($actualData['transactions']) > 1
                && null !== $actualData['transactions'][0]['transaction_time']
                && null !== $actualData['transactions'][1]['transaction_time']
            ) {
                $this->assertGreaterThan(
                    $actualData['transactions'][0]['transaction_time'],
                    $actualData['transactions'][1]['transaction_time'],
                );
            }

            foreach ($responseData['Transactions'] as $key => $tx) {
                if (isset($tx['CreateDate'])) {
                    $this->assertSame($actualData['transactions'][$key]['transaction_time']->format('YmdHis'), $tx['CreateDate']);
                    $this->assertEquals($expectedData['transactions'][$key]['capture_time'], $actualData['transactions'][$key]['capture_time']);
                    unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
                    unset($actualData['transactions'][$key]['capture_time'], $expectedData['transactions'][$key]['capture_time']);
                }

                \ksort($actualData['transactions'][$key]);
                \ksort($expectedData['transactions'][$key]);
            }
        }

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DPaymentResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPaymentData([], [], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
    }

    public static function paymentDataProvider(): iterable
    {
        yield 'success1' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'             => '202312053421',
                'BankResponseCode'    => '00',
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => '2000000000032562',
                'CardHolderName'      => null,
                'Code'                => 0,
                'Message'             => 'Başarılı',
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => '2000000000032562',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(),
                'auth_code'         => null,
                'order_id'          => '202312053421',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
            ],
        ];
        yield 'success_post_pay' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'             => '202312053F93',
                'BankResponseCode'    => '00',
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => '2000000000032560',
                'Code'                => 0,
                'Message'             => 'Başarılı',
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => '2000000000032560',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(),
                'auth_code'         => null,
                'order_id'          => '202312053F93',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
            ],
        ];
        yield 'error_post_pay' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'             => '202312053F93',
                'BankResponseCode'    => null,
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => null,
                'Code'                => 101,
                'Message'             => 'Orjinal Kayıt Bulunamadı',
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '202312053F93',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => null,
                'status'            => 'declined',
                'status_detail'     => 'transaction_not_found',
                'error_code'        => null,
                'error_message'     => 'Orjinal Kayıt Bulunamadı',
                'installment_count' => null,
            ],
        ];
        yield 'error_hash_error' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'             => null,
                'BankResponseCode'    => null,
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => null,
                'CardHolderName'      => null,
                'Code'                => 997,
                'Message'             => 'Hash Hatası',
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => null,
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => null,
                'error_message'     => 'Hash Hatası',
                'installment_count' => null,
            ],
        ];
    }

    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'  => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '202312034E91',
                    'MdStatus'            => '1',
                    'ThreeDSessionId'     => 'P40D18956D9C94188ABF6C87B37075AF7B1029577C4BF4BADB8E86058919000F4',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '1',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => new \DateTimeImmutable(),
                    'transaction_type'     => 'pay',
                    'transaction_security' => 'Full 3D Secure',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => '1',
                    'tx_status'            => 'PAYMENT_COMPLETED',
                    'md_error_message'     => null,
                    'order_id'             => '202312034E91',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => null,
                ],
            ],
            'auth_fail' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '20231203E148',
                    'MdStatus'            => '0',
                    'ThreeDSessionId'     => 'P2462E945F4554146B8E4A4306B7FF6C16D4047086D304B61B53430BD7CD02F51',
                    'BankResponseCode'    => 'MD:0',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '0',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'C7Vbcr3adDhlWEr9vT9oFHikjrjEiv5DSBORu0YnOATkF/YirOziwouAGk8vqB29oeyPBnlFgBih7bLN9YWweQ==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'status_detail'        => null,
                    'md_status'            => '0',
                    'tx_status'            => 'ERROR',
                    'md_error_message'     => null,
                    'order_id'             => '20231203E148',
                    'proc_return_code'     => 'MD:0',
                    'status'               => 'declined',
                    'error_code'           => 'MD:0',
                    'error_message'        => null,
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => null,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '20231203626F',
                    'MdStatus'            => '1',
                    'ThreeDSessionId'     => 'P8A6DB3F7FDB74A3F903C44883401F178609178BC431C47DE92E4811587C65589',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '1',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'A+bjgxp/uIQjpsY+cEpUJcu+m6xXMgpDz7DOjtQ8TgKgJaFFsLGKkpNKOYzInqfkJ6U9+S8mxGFBv4o4WqC4hg==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'transaction_security' => 'Full 3D Secure',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => '1',
                    'tx_status'            => 'PAYMENT_COMPLETED',
                    'md_error_message'     => null,
                    'order_id'             => '20231203626F',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'payment_model'        => '3d_host',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => null,
                ],
            ],
        ];
    }

    public static function statusResponseDataProvider(): \Generator
    {
        $txTime = new \DateTimeImmutable('20240120005007');
        yield 'success_pay' => [
            'responseData' => [
                'TransactionType'          => 1,
                'CreateDate'               => '20240120005007',
                'OrderId'                  => '202401199AAA',
                'BankResponseCode'         => '00',
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 1,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 98,
                'MerchantCommissionAmount' => 3,
                'MerchantCommissionRate'   => 3,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => '202401199AAA',
                'auth_code'         => null,
                'proc_return_code'  => '00',
                'transaction_id'    => null,
                'transaction_time'  => $txTime,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'pay',
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => true,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => $txTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        $txTime = new \DateTimeImmutable('20231205224003');
        yield 'success_pre_pay_then_cancel' => [
            'responseData' => [
                'TransactionType'          => 2,
                'CreateDate'               => '20231205224003',
                'OrderId'                  => '20231205D497',
                'BankResponseCode'         => '00',
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 2,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 101,
                'MerchantCommissionAmount' => 0,
                'MerchantCommissionRate'   => null,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => null,
            ],
            'expectedData' => [
                'order_id'          => '20231205D497',
                'auth_code'         => null,
                'proc_return_code'  => '00',
                'transaction_id'    => null,
                'transaction_time'  => $txTime,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'CANCELED',
                'transaction_type'  => 'pre',
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => true,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => $txTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        yield 'fail_waiting_for_3d_auth' => [
            'responseData' => [
                'TransactionType'          => 1,
                'CreateDate'               => '20231204002334',
                'OrderId'                  => '20231203CA6D',
                'BankResponseCode'         => null,
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => null,
                'RequestStatus'            => 10,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 101,
                'MerchantCommissionAmount' => 0,
                'MerchantCommissionRate'   => null,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => '20231203CA6D',
                'auth_code'         => null,
                'proc_return_code'  => null,
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable('20231204002334'),
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => null,
                'order_status'      => 10,
                'transaction_type'  => 'pay',
                'capture_amount'    => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => null,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        $txTime = new \DateTimeImmutable('20240119230959');
        yield 'success_pre_auth' => [
            'responseData' => [
                'TransactionType'          => 2,
                'CreateDate'               => '20240119230959',
                'OrderId'                  => '202401196E94',
                'BankResponseCode'         => '00',
                'BankResponseMessage'      => '',
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 1,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 98,
                'MerchantCommissionAmount' => 3,
                'MerchantCommissionRate'   => 3,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => '202401196E94',
                'auth_code'         => null,
                'proc_return_code'  => '00',
                'transaction_id'    => null,
                'transaction_time'  => $txTime,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'pre',
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => true,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => $txTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        $txTime = new \DateTimeImmutable('20231210132528');
        yield 'success_pre_auth_then_post_auth' => [
            'responseData' => [
                'TransactionType'          => 2,
                'CreateDate'               => '20231210132528',
                'OrderId'                  => '20231210A7D0',
                'BankResponseCode'         => '00',
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 2,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 5,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 101,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 101,
                'MerchantCommissionAmount' => 0,
                'MerchantCommissionRate'   => null,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => null,
            ],
            'expectedData' => [
                'order_id'          => '20231210A7D0',
                'auth_code'         => null,
                'proc_return_code'  => '00',
                'transaction_id'    => null,
                'transaction_time'  => $txTime,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'PRE_AUTH_COMPLETED',
                'transaction_type'  => 'pre',
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => true,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => $txTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 2,
            ],
        ];

        $txTime = new \DateTimeImmutable('20240120005901');
        yield 'success_pay_and_partial_refund' => [
            'responseData' => [
                'TransactionType'          => 1,
                'CreateDate'               => '20240120005901',
                'OrderId'                  => '20240119E16A',
                'BankResponseCode'         => '00',
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 101,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 3,
                'RefundedAmount'           => 59,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 98,
                'MerchantCommissionAmount' => 3,
                'MerchantCommissionRate'   => 3,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => '20240119E16A',
                'auth_code'         => null,
                'proc_return_code'  => '00',
                'transaction_id'    => null,
                'transaction_time'  => $txTime,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'PARTIALLY_REFUNDED',
                'transaction_type'  => 'pay',
                'capture_amount'    => 1.01,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => true,
                'currency'          => 'TRY',
                'first_amount'      => 1.01,
                'capture_time'      => $txTime,
                'cancel_time'       => null,
                'refund_amount'     => 0.59,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        yield 'fail_order_not_found' => [
            'responseData' => [
                'TransactionType'          => 0,
                'CreateDate'               => null,
                'OrderId'                  => null,
                'BankResponseCode'         => null,
                'BankResponseMessage'      => null,
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 0,
                'Currency'                 => 0,
                'InstallmentCount'         => 0,
                'ClientId'                 => 0,
                'CardNo'                   => null,
                'RequestStatus'            => 0,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 0,
                'MerchantCommissionAmount' => 0,
                'MerchantCommissionRate'   => null,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => null,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => null,
                'auth_code'         => null,
                'proc_return_code'  => null,
                'transaction_id'    => null,
                'transaction_time'  => null,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => null,
                'order_status'      => 'ERROR',
                'transaction_type'  => null,
                'capture_amount'    => null,
                'capture_time'      => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => null,
                'currency'          => null,
                'first_amount'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        yield 'fail_unsuccessful_payment' => [
            'responseData' => [
                'TransactionType'          => 2,
                'CreateDate'               => '20240119231357',
                'OrderId'                  => '202401195754',
                'BankResponseCode'         => 'MD:0',
                'BankResponseMessage'      => '',
                'AuthCode'                 => null,
                'HostReferenceNumber'      => null,
                'Amount'                   => 200,
                'Currency'                 => 949,
                'InstallmentCount'         => 0,
                'ClientId'                 => 1000000494,
                'CardNo'                   => '41595600****7732',
                'RequestStatus'            => 0,
                'RefundedAmount'           => 0,
                'PostAuthedAmount'         => 0,
                'TransactionId'            => 0,
                'CommissionStatus'         => null,
                'NetAmount'                => 200,
                'MerchantCommissionAmount' => 0,
                'MerchantCommissionRate'   => null,
                'CardBankId'               => 0,
                'CardTypeId'               => 0,
                'ValorDate'                => 0,
                'TransactionDate'          => 0,
                'BankValorDate'            => 0,
                'ExtraParameters'          => null,
                'Code'                     => 0,
                'Message'                  => '',
            ],
            'expectedData' => [
                'order_id'          => '202401195754',
                'auth_code'         => null,
                'proc_return_code'  => 'MD:0',
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable('20240119231357'),
                'error_message'     => null,
                'ref_ret_num'       => null,
                'masked_number'     => '41595600****7732',
                'order_status'      => 'ERROR',
                'transaction_type'  => 'pre',
                'capture_amount'    => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => 'approved',
                'capture'           => null,
                'currency'          => 'TRY',
                'first_amount'      => 2.0,
                'capture_time'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'OrderId'             => '202312058278',
                        'BankResponseCode'    => '00',
                        'BankResponseMessage' => null,
                        'AuthCode'            => null,
                        'HostReferenceNumber' => null,
                        'TransactionId'       => '2000000000032548',
                        'Code'                => 0,
                        'Message'             => 'Başarılı',
                    ],
                    'expectedData' => [
                        'order_id'         => '202312058278',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '00',
                        'transaction_id'   => '2000000000032548',
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail_1'   => [
                    'responseData' => [
                        'OrderId'             => '20231203CA6D',
                        'BankResponseCode'    => null,
                        'BankResponseMessage' => null,
                        'AuthCode'            => null,
                        'HostReferenceNumber' => null,
                        'TransactionId'       => null,
                        'Code'                => 101,
                        'Message'             => 'Orjinal Kayıt Bulunamadı',
                    ],
                    'expectedData' => [
                        'order_id'         => '20231203CA6D',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => null,
                        'transaction_id'   => null,
                        'error_code'       => 101,
                        'error_message'    => 'Orjinal Kayıt Bulunamadı',
                        'status'           => 'declined',
                        'status_detail'    => 'transaction_not_found',
                    ],
                ],
            ];
    }

    public static function refundDataProvider(): array
    {
        return [
            'fail1'    => [
                'responseData' => [
                    'OrderId'             => null,
                    'BankResponseCode'    => null,
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => null,
                    'Code'                => 999,
                    'Message'             => 'Genel Hata',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => null,
                    'transaction_id'   => null,
                    'error_code'       => 999,
                    'error_message'    => 'Genel Hata',
                    'status'           => 'declined',
                    'status_detail'    => 'general_error',
                ],
            ],
            'fail2'    => [
                'responseData' => [
                    'OrderId'             => '202312051B4E',
                    'BankResponseCode'    => null,
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => null,
                    'Code'                => 101,
                    'Message'             => 'Orjinal Kayıt Bulunamadı',
                ],
                'expectedData' => [
                    'order_id'         => '202312051B4E',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => null,
                    'transaction_id'   => null,
                    'error_code'       => 101,
                    'error_message'    => 'Orjinal Kayıt Bulunamadı',
                    'status'           => 'declined',
                    'status_detail'    => 'transaction_not_found',
                ],
            ],
            'success1' => [
                'responseData' => [
                    'OrderId'             => '202312051B4E',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => '2000000000032550',
                    'Code'                => 0,
                    'Message'             => 'Başarılı',
                ],
                'expectedData' => [
                    'order_id'         => '202312051B4E',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => '00',
                    'transaction_id'   => '2000000000032550',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
        ];
    }

    public static function orderHistoryDataProvider(): array
    {
        return [
            'fail_validation'                  => [
                'responseData' => [
                    'Code'             => 998,
                    'message'          => 'Validasyon Hatası',
                    'ValidationErrors' => [
                        "Could not convert string to integer: 20231209123936. Path 'transactionDate', line 1, position 113.'",
                    ],
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'proc_return_code' => null,
                    'error_code'       => 998,
                    'error_message'    => 'Validasyon Hatası',
                    'status'           => 'declined',
                    'status_detail'    => 'invalid_transaction',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'fail_when_no_hash_value_sent'     => [
                'responseData' => [
                    'Count'        => 0,
                    'Transactions' => null,
                    'Code'         => 999,
                    'Message'      => 'Genel Hata',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'proc_return_code' => null,
                    'error_code'       => 999,
                    'error_message'    => 'Genel Hata',
                    'status'           => 'declined',
                    'status_detail'    => 'general_error',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'success_no_order_found'           => [
                'responseData' => [
                    'Count'        => 0,
                    'Transactions' => [],
                    'Code'         => 0,
                    'Message'      => 'Başarılı',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'success_only_payment_transaction' => [
                'responseData' => [
                    'Count'        => 1,
                    'Code'         => 0,
                    'Message'      => 'Başarılı',
                    'Transactions' => [
                        [
                            'TransactionType'          => 1,
                            'CreateDate'               => '20231209154531',
                            'OrderId'                  => '20231209C3AE',
                            'BankResponseCode'         => '00',
                            'BankResponseMessage'      => null,
                            'AuthCode'                 => null,
                            'HostReferenceNumber'      => null,
                            'Amount'                   => 101,
                            'Currency'                 => 949,
                            'InstallmentCount'         => 0,
                            'ClientId'                 => '1000000494',
                            'CardNo'                   => '41595600****7732',
                            'RequestStatus'            => 1,
                            'RefundedAmount'           => 0,
                            'PostAuthedAmount'         => 0,
                            'TransactionId'            => '2000000000032596',
                            'CommissionStatus'         => null,
                            'NetAmount'                => 101,
                            'MerchantCommissionAmount' => 0,
                            'MerchantCommissionRate'   => null,
                            'CardBankId'               => 13,
                            'CardTypeId'               => 1,
                            'ValorDate'                => 0,
                            'TransactionDate'          => '20231209',
                            'BankValorDate'            => 0,
                            'ExtraParameters'          => null,
                            'Code'                     => 0,
                            'Message'                  => 'Success',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20231209C3AE',
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'order_id'         => '20231209C3AE',
                            'auth_code'        => null,
                            'proc_return_code' => '00',
                            'transaction_id'   => '2000000000032596',
                            'transaction_time' => new \DateTimeImmutable('2023-12-09 15:45:31'),
                            'capture_time'     => new \DateTimeImmutable('2023-12-09 15:45:31'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'masked_number'    => '41595600****7732',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'first_amount'     => 1.01,
                        ],
                    ],
                ],
            ],
            'success_multiple_transactions'    => [
                'responseData' => [
                    'Count'        => 2,
                    'Code'         => 0,
                    'Message'      => 'Başarılı',
                    'Transactions' => [
                        [
                            'TransactionType'          => 1,
                            'CreateDate'               => '20231209154531',
                            'OrderId'                  => '20231209C3AE',
                            'BankResponseCode'         => '00',
                            'BankResponseMessage'      => null,
                            'AuthCode'                 => null,
                            'HostReferenceNumber'      => null,
                            'Amount'                   => 101,
                            'Currency'                 => 949,
                            'InstallmentCount'         => 0,
                            'ClientId'                 => '1000000494',
                            'CardNo'                   => '41595600****7732',
                            'RequestStatus'            => 2,
                            'RefundedAmount'           => 0,
                            'PostAuthedAmount'         => 0,
                            'TransactionId'            => '2000000000032596',
                            'CommissionStatus'         => null,
                            'NetAmount'                => '101',
                            'MerchantCommissionAmount' => 0,
                            'MerchantCommissionRate'   => null,
                            'CardBankId'               => 13,
                            'CardTypeId'               => 1,
                            'ValorDate'                => 0,
                            'TransactionDate'          => '20231209',
                            'BankValorDate'            => 0,
                            'ExtraParameters'          => null,
                            'Code'                     => 0,
                            'Message'                  => 'Success',
                        ],
                        [
                            'TransactionType'          => 4,
                            'CreateDate'               => '20231209154644',
                            'OrderId'                  => '20231209C3AE',
                            'BankResponseCode'         => '00',
                            'BankResponseMessage'      => null,
                            'AuthCode'                 => null,
                            'HostReferenceNumber'      => null,
                            'Amount'                   => 101,
                            'Currency'                 => 949,
                            'InstallmentCount'         => 0,
                            'ClientId'                 => 1000000494,
                            'CardNo'                   => '41595600****7732',
                            'RequestStatus'            => 1,
                            'RefundedAmount'           => 0,
                            'PostAuthedAmount'         => 0,
                            'TransactionId'            => 2000000000032597,
                            'CommissionStatus'         => null,
                            'NetAmount'                => 101,
                            'MerchantCommissionAmount' => 0,
                            'MerchantCommissionRate'   => null,
                            'CardBankId'               => 13,
                            'CardTypeId'               => 1,
                            'ValorDate'                => 0,
                            'TransactionDate'          => 20231209,
                            'BankValorDate'            => 0,
                            'ExtraParameters'          => null,
                            'Code'                     => 0,
                            'Message'                  => 'Başarılı',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20231209C3AE',
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'order_id'         => '20231209C3AE',
                            'auth_code'        => null,
                            'proc_return_code' => '00',
                            'transaction_id'   => '2000000000032596',
                            'transaction_time' => new \DateTimeImmutable('2023-12-09 15:45:31'),
                            'capture_time'     => new \DateTimeImmutable('2023-12-09 15:45:31'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'masked_number'    => '41595600****7732',
                            'order_status'     => 'CANCELED',
                            'transaction_type' => 'pay',
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'first_amount'     => 1.01,
                        ],
                        [
                            'order_id'         => '20231209C3AE',
                            'auth_code'        => null,
                            'proc_return_code' => '00',
                            'transaction_id'   => 2000000000032597,
                            'transaction_time' => new \DateTimeImmutable('20231209154644'),
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'masked_number'    => '41595600****7732',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'cancel',
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'first_amount'     => 1.01,
                        ],
                    ],
                ],
            ],
        ];
    }
}
