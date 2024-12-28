<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class PosNetResponseDataMapperTest extends TestCase
{
    private PosNetResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new PosNetRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );
        $this->responseDataMapper = new PosNetResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["2", true]
     * ["3", true]
     * ["4", true]
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
     * [{"oosResolveMerchantDataResponse": {"mdStatus": "1"}}, "1"]
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

        \ksort($expectedData);
        \ksort($actualData);
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
     * @dataProvider refundTestDataProvider
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

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
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

    public function paymentTestDataProvider(): array
    {
        return
            [
                'success1' => [
                    'order'        => [
                        'id'       => '202312171800ABC',
                        'currency' => PosInterface::CURRENCY_TRY,
                        'amount'   => 1.01,
                    ],
                    'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                    'responseData' => [
                        'approved'   => '1',
                        'respCode'   => '',
                        'respText'   => '00',
                        'mac'        => 'DF2323A3BMC782QOP42RT',
                        'hostlogkey' => '0000000002P0806031',
                        'authCode'   => '901477',
                        'instInfo'   => [
                            'inst1' => '00',
                            'amnt1' => '000000000000',
                        ],
                        'pointInfo'  => [
                            'point'            => '00000228',
                            'pointAmount'      => '000000000114',
                            'totalPoint'       => '00000000',
                            'totalPointAmount' => '000000000000',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'          => '202312171800ABC',
                        'transaction_id'    => null,
                        'transaction_type'  => 'pay',
                        'transaction_time'  => new \DateTimeImmutable(),
                        'installment_count' => 0,
                        'currency'          => 'TRY',
                        'amount'            => 1.01,
                        'payment_model'     => 'regular',
                        'auth_code'         => '901477',
                        'ref_ret_num'       => '0000000002P0806031',
                        'batch_num'         => null,
                        'proc_return_code'  => '1',
                        'status'            => 'approved',
                        'status_detail'     => 'approved',
                        'error_code'        => null,
                        'error_message'     => '00',
                    ],
                ],
                'fail1'    => [
                    'order'        => [
                        'id'       => '202312171800ABC',
                        'currency' => PosInterface::CURRENCY_TRY,
                        'amount'   => 1.01,
                    ],
                    'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                    'responseData' => [
                        'approved' => '0',
                        'respCode' => '0148',
                        'respText' => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                    'expectedData' => [
                        'order_id'          => '202312171800ABC',
                        'transaction_id'    => null,
                        'transaction_type'  => 'pay',
                        'transaction_time'  => null,
                        'installment_count' => null,
                        'currency'          => 'TRY',
                        'amount'            => 1.01,
                        'payment_model'     => 'regular',
                        'auth_code'         => null,
                        'ref_ret_num'       => null,
                        'batch_num'         => null,
                        'proc_return_code'  => '0',
                        'status'            => 'declined',
                        'status_detail'     => null,
                        'error_code'        => '0148',
                        'error_message'     => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                ],
                'fail2'    => [
                    'order'        => [
                        'id'       => '202312171800ABC',
                        'currency' => PosInterface::CURRENCY_TRY,
                        'amount'   => 1.01,
                    ],
                    'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                    'responseData' => [
                        'order'      => [
                            'id'       => '202312171800ABC',
                            'currency' => PosInterface::CURRENCY_TRY,
                            'amount'   => 1.01,
                        ],
                        'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
                        'approved'   => '2',
                        'respCode'   => '0127',
                        'respText'   => 'ORDERID DAHA ONCE KULLANILMIS 0127',
                        'hostlogkey' => '020527337090000191',
                        'authCode'   => '273370',
                        'tranDate'   => '190703093340',
                    ],
                    'expectedData' => [
                        'order_id'          => '202312171800ABC',
                        'transaction_id'    => null,
                        'transaction_type'  => 'pay',
                        'transaction_time'  => null,
                        'installment_count' => null,
                        'currency'          => 'TRY',
                        'amount'            => 1.01,
                        'payment_model'     => 'regular',
                        'auth_code'         => '273370',
                        'ref_ret_num'       => '020527337090000191',
                        'batch_num'         => null,
                        'proc_return_code'  => '2',
                        'status'            => 'declined',
                        'status_detail'     => null,
                        'error_code'        => '0127',
                        'error_message'     => 'ORDERID DAHA ONCE KULLANILMIS 0127',
                    ],
                ],
            ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'success1'                               => [
                'order'              => [
                    'id' => '80603153823',
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'approved'                       => '1',
                    'respCode'                       => '',
                    'respText'                       => '',
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'installment'    => '00',
                        'point'          => '0',
                        'pointAmount'    => '0',
                        'txStatus'       => 'Y',
                        'mdStatus'       => '1',
                        'mdErrorMessage' => '',
                        'mac'            => 'y0fU6rRA0OvqJ5GN6uMdHVu6Xra7QR1qeT9rN7R1L+o=',
                    ],
                ],
                'paymentData'        => [
                    'approved'   => '1',
                    'respCode'   => '',
                    'respText'   => '00',
                    'mac'        => 'DF2323A3BMC782QOP42RT',
                    'hostlogkey' => '0000000002P0806031',
                    'authCode'   => '901477',
                    'instInfo'   => [
                        'inst1' => '00',
                        'amnt1' => '000000000000',
                    ],
                    'pointInfo'  => [
                        'point'            => '00000228',
                        'pointAmount'      => '000000000114',
                        'totalPoint'       => '00000000',
                        'totalPointAmount' => '000000000000',
                    ],
                ],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'md_error_message'     => null,
                    'installment_count'    => 0,
                    'auth_code'            => '901477',
                    'ref_ret_num'          => '0000000002P0806031',
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => '00',
                    'order_id'             => '80603153823',
                    'remote_order_id'      => 'YKB_0000080603153823',
                    'proc_return_code'     => '1',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 56.96,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                ],
            ],
            'auth_fail1'                             => [
                'order'              => [
                    'id' => '80603153823',
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'installment'    => '00',
                        'point'          => '0',
                        'pointAmount'    => '0',
                        'txStatus'       => 'N',
                        'mdStatus'       => '9',
                        'mdErrorMessage' => 'None 3D - Secure Transaction',
                        'mac'            => 'ED7254A3ABC264QOP67MN',
                    ],
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '9',
                    'md_error_message'     => 'None 3D - Secure Transaction',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '80603153823',
                    'remote_order_id'      => 'YKB_0000080603153823',
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'amount'               => 56.96,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            'fail2-md-empty'                         => [
                'order'              => [
                    'id' => '80603153823',
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'installment'    => '00',
                        'point'          => '0',
                        'pointAmount'    => '0',
                        'txStatus'       => 'N',
                        'mdStatus'       => '',
                        'mdErrorMessage' => 'None 3D - Secure Transaction',
                        'mac'            => 'ED7254A3ABC264QOP67MN',
                    ],
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'md_status'            => null,
                    'md_error_message'     => 'None 3D - Secure Transaction',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '80603153823',
                    'remote_order_id'      => 'YKB_0000080603153823',
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'amount'               => 56.96,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            'fail_no_oosResolveMerchantDataResponse' => [
                'order'              => [
                    'id' => '80603153823',
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'approved' => '0',
                    'respCode' => 'E216',
                    'respText' => 'Mac Do\u011frulama hatal\u0131',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_id'    => null,
                    'transaction_type'  => 'pay',
                    'transaction_time'  => null,
                    'amount'            => null,
                    'currency'          => null,
                    'installment_count' => null,
                    'payment_model'     => '3d',
                    'order_id'          => null,
                    'auth_code'         => null,
                    'ref_ret_num'       => null,
                    'batch_num'         => null,
                    'proc_return_code'  => '0',
                    'status'            => 'declined',
                    'status_detail'     => null,
                    'error_code'        => 'E216',
                    'error_message'     => 'Mac Do\u011frulama hatal\u0131',
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): array
    {
        return [
            'success1' => [
                'responseData' => [
                    'approved'     => '1',
                    'transactions' => [
                        'transaction' => [
                            'orderID'      => 'TDS_YKB_0000191010111730',
                            'ccno'         => '4506 34** **** 4637',
                            'amount'       => '1,16',
                            'currencyCode' => 'TL',
                            'authCode'     => '504289',
                            'tranDate'     => '2019-10-10 11:21:14.281',
                            'state'        => 'Sale',
                            'txnStatus'    => '1',
                            'hostlogkey'   => '021450428990000191',
                        ],
                    ],
                ],
                'expectedData' => [
                    'auth_code'         => '504289',
                    'transaction_id'    => null,
                    'ref_ret_num'       => '021450428990000191',
                    'transaction_time'  => new \DateTimeImmutable('2019-10-10 11:21:14.281'),
                    'transaction_type'  => 'pay',
                    'proc_return_code'  => '1',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => null,
                    'capture_amount'    => null,
                    'first_amount'      => 1.16,
                    'masked_number'     => null,
                    'order_id'          => 'TDS_YKB_0000191010111730',
                    'order_status'      => null,
                    'capture_time'      => null,
                    'currency'          => 'TRY',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'fail1'    => [
                'responseData' => [
                    'approved' => '0',
                    'respCode' => '0148',
                    'respText' => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                ],
                'expectedData' => [
                    'auth_code'         => null,
                    'transaction_id'    => null,
                    'ref_ret_num'       => null,
                    'transaction_type'  => null,
                    'capture'           => null,
                    'capture_amount'    => null,
                    'capture_time'      => null,
                    'currency'          => null,
                    'transaction_time'  => null,
                    'first_amount'      => null,
                    'masked_number'     => null,
                    'order_id'          => null,
                    'order_status'      => null,
                    'proc_return_code'  => '0',
                    'status'            => 'declined',
                    'status_detail'     => 'declined',
                    'error_code'        => '0148',
                    'error_message'     => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
        ];
    }

    public function cancelTestDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'AuthCode'       => 'S05229',
                        'HostRefNum'     => '230423103898',
                        'ProcReturnCode' => '00',
                        'TransId'        => '20221031D388',
                        'ErrMsg'         => 'Onaylandı',
                        'CardHolderName' => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20221031D388',
                        'auth_code'        => 'S05229',
                        'ref_ret_num'      => '230423103898',
                        'proc_return_code' => '00',
                        'transaction_id'   => null,
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail1'    => [
                    'responseData' => [
                        'AuthCode'       => '',
                        'HostRefNum'     => '230423103927',
                        'ProcReturnCode' => 'V013',
                        'TransId'        => '20221031D388',
                        'ErrMsg'         => 'Seçili İşlem Bulunamadı!',
                        'CardHolderName' => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20221031D388',
                        'auth_code'        => null,
                        'ref_ret_num'      => '230423103927',
                        'proc_return_code' => 'V013',
                        'transaction_id'   => null,
                        'error_code'       => 'V013',
                        'error_message'    => 'Seçili İşlem Bulunamadı!',
                        'status'           => 'declined',
                        'status_detail'    => 'reject',
                    ],
                ],
            ];
    }

    public function refundTestDataProvider(): array
    {
        return
            [
                'fail1' => [
                    'responseData' => [
                        'approved' => '0',
                        'respCode' => '0148',
                        'respText' => 'INVALID MID TID IP. Hatalı IP:89.244.149.137',
                    ],
                    'expectedData' => [
                        'auth_code'        => null,
                        'transaction_id'   => null,
                        'ref_ret_num'      => null,
                        'group_id'         => null,
                        'date'             => null,
                        'transaction_type' => null,
                        'proc_return_code' => '0',
                        'status'           => 'declined',
                        'status_detail'    => 'declined',
                        'error_code'       => '0148',
                        'error_message'    => 'INVALID MID TID IP. Hatalı IP:89.244.149.137',
                    ],
                ],
            ];
    }
}
