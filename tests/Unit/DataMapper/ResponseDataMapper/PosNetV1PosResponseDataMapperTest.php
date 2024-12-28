<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class PosNetV1PosResponseDataMapperTest extends TestCase
{
    private PosNetV1PosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();


        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new PosNetV1PosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );
        $this->responseDataMapper = new PosNetV1PosResponseDataMapper(
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

        if ([] !== $paymentResponse) {
            $this->assertArrayHasKey('all', $actualData);
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
     * @dataProvider mapStatusResponseDataProvider
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
     * @dataProvider mapCancelResponseDataProvider
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
     * @dataProvider mapRefundResponseDataProvider
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

    public function testMapOrderHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
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

    public static function paymentTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'order'        => [
                'id'       => '202312171800ABC',
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentData'  => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '0127',
                    'ResponseDescription' => 'ORDERID DAHA ONCE KULLANILMIS',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
                'PointDataList'       => null,
                'InstallmentData'     => null,
                'MessageData'         => null,
            ],
            'expectedData' => [
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'payment_model'     => 'regular',
                'order_id'          => '202312171800ABC',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => '0127',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => '0127',
                'error_message'     => 'ORDERID DAHA ONCE KULLANILMIS',
                'installment_count' => null,
            ],
        ];
        yield 'success1' => [
            'order'        => [
                'id'       => '202312171800ABC',
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentData'  => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '00',
                    'ResponseDescription' => 'Onaylandı',
                ],
                'AuthCode'            => '449324',
                'ReferenceCode'       => '159044932490000231',
                'PointDataList'       => [
                    [
                        'PointType'     => 'EarnedPoint',
                        'Point'         => 1000,
                        'PointTLAmount' => 500,
                    ],
                    [
                        'PointType'     => 'TotalPoint',
                        'Point'         => 94739396,
                        'PointTLAmount' => 347369698,
                    ],
                    [
                        'PointType'     => 'EarnedStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 500,
                    ],
                    [
                        'PointType'     => 'EarnedStandartMrc',
                        'Point'         => 0,
                        'PointTLAmount' => 250,
                    ],
                    [
                        'PointType'     => 'EarnedLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'EarnedLoyaltyMrc',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'UsedStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'UsedLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'AvailableStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 347369698,
                    ],
                    [
                        'PointType'     => 'AvailableLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                ],
                'InstallmentData'     => [
                    'InstallmentCount' => 0,
                    'Amount'           => 0,
                ],
                'MessageData'         => [
                    'Message1' => ' ',
                    'Message2' => null,
                    'Message3' => null,
                    'Message4' => null,
                ],
            ],
            'expectedData' => [
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable(),
                'transaction_type'  => 'pay',
                'payment_model'     => 'regular',
                'order_id'          => '202312171800ABC',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'auth_code'         => '449324',
                'ref_ret_num'       => '159044932490000231',
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => 0,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): \Generator
    {
        yield '3d_auth_fail_1' => [
            'order'              => [
                'id' => '20230622A1C9',
            ],
            'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'threeDResponseData' => [
                'CCPrefix'            => '450634',
                'TranType'            => 'Sale',
                'Amount'              => '101',
                'OrderId'             => '0000000020230622A1C9',
                'MerchantId'          => '6702640212',
                'CAVV'                => null,
                'CAVVAlgorithm'       => null,
                'ECI'                 => '07',
                'MD'                  => '0161010063138198543',
                'MdErrorMessage'      => 'Not authenticated',
                'MdStatus'            => '0',
                'SecureTransactionId' => '1010063138198543',
                'Mac'                 => 'ltpqSazdMf67AjmWF0WQ5pOU78F+kjrfkyz7ex+ZvNg=',
                'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
            ],
            'paymentData'        => [],
            'expectedData'       => [
                'transaction_id'       => null,
                'transaction_type'     => 'pay',
                'transaction_time'     => null,
                'transaction_security' => 'MPI fallback',
                'masked_number'        => '450634',
                'md_status'            => '0',
                'md_error_message'     => 'Not authenticated',
                'amount'               => 1.01,
                'auth_code'            => null,
                'installment_count'    => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => '20230622A1C9',
                'remote_order_id'      => '0000000020230622A1C9',
                'proc_return_code'     => null,
                'status'               => 'declined',
                'currency'             => null,
                'payment_model'        => '3d',
            ],
        ];
        yield 'success1' => [
            'order'              => [
                'id' => '80603153823',
            ],
            'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'threeDResponseData' => [
                'CCPrefix'            => '540061',
                'TranType'            => 'Sale',
                'Amount'              => '175',
                'OrderId'             => 'ALA_0000080603153823',
                'MerchantId'          => '6700950031',
                'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                'CAVVAlgorithm'       => '3',
                'ECI'                 => '02',
                'MD'                  => '0161010028947569644,0161010028947569644',
                'MdErrorMessage'      => 'Authenticated',
                'MdStatus'            => '1',
                'SecureTransactionId' => '1010028947569644',
                'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                'CurrencyCode'        => '949',
                'InstalmentCode'      => '0',
                'VtfCode'             => '',
                'PointAmount'         => '',
            ],
            'paymentData'        => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '00',
                    'ResponseDescription' => 'Onaylandı',
                ],
                'AuthCode'            => '449324',
                'ReferenceCode'       => '159044932490000231',
                'PointDataList'       => [
                    [
                        'PointType'     => 'EarnedPoint',
                        'Point'         => 1000,
                        'PointTLAmount' => 500,
                    ],
                    [
                        'PointType'     => 'TotalPoint',
                        'Point'         => 94739396,
                        'PointTLAmount' => 347369698,
                    ],
                    [
                        'PointType'     => 'EarnedStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 500,
                    ],
                    [
                        'PointType'     => 'EarnedStandartMrc',
                        'Point'         => 0,
                        'PointTLAmount' => 250,
                    ],
                    [
                        'PointType'     => 'EarnedLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'EarnedLoyaltyMrc',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'UsedStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'UsedLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                    [
                        'PointType'     => 'AvailableStandartTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 347369698,
                    ],
                    [
                        'PointType'     => 'AvailableLoyaltyTotal',
                        'Point'         => 0,
                        'PointTLAmount' => 0,
                    ],
                ],
                'InstallmentData'     => [
                    'InstallmentCount' => 0,
                    'Amount'           => 0,
                ],
                'MessageData'         => [
                    'Message1' => ' ',
                    'Message2' => null,
                    'Message3' => null,
                    'Message4' => null,
                ],
            ],
            'expectedData'       => [
                'transaction_id'       => null,
                'transaction_type'     => 'pay',
                'transaction_time'     => new \DateTimeImmutable(),
                'transaction_security' => 'Full 3D Secure',
                'masked_number'        => '540061',
                'md_status'            => '1',
                'md_error_message'     => null,
                'amount'               => 1.75,
                'auth_code'            => '449324',
                'ref_ret_num'          => '159044932490000231',
                'batch_num'            => null,
                'status_detail'        => 'approved',
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => '80603153823',
                'remote_order_id'      => 'ALA_0000080603153823',
                'proc_return_code'     => '00',
                'status'               => 'approved',
                'currency'             => 'TRY',
                'payment_model'        => '3d',
                'installment_count'    => 0,
            ],
        ];

        yield 'threeDAuthFail2' => [
            'order'              => [
                'id' => '80603153823',
            ],
            'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'threeDResponseData' => [
                'CCPrefix'            => '540061',
                'TranType'            => 'Sale',
                'Amount'              => '175',
                'OrderId'             => 'ALA_0000080603153823',
                'MerchantId'          => '6700950031',
                'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                'CAVVAlgorithm'       => '3',
                'ECI'                 => '02',
                'MD'                  => '0161010028947569644,0161010028947569644',
                'MdErrorMessage'      => 'Error',
                'MdStatus'            => '0',
                'SecureTransactionId' => '1010028947569644',
                'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                'CurrencyCode'        => '949',
                'InstalmentCode'      => '0',
                'VtfCode'             => '',
                'PointAmount'         => '',
            ],
            'paymentData'        => [],
            'expectedData'       => [
                'transaction_id'       => null,
                'transaction_type'     => 'pay',
                'transaction_time'     => null,
                'transaction_security' => 'MPI fallback',
                'masked_number'        => '540061',
                'md_status'            => '0',
                'md_error_message'     => 'Error',
                'amount'               => 1.75,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => '80603153823',
                'remote_order_id'      => 'ALA_0000080603153823',
                'proc_return_code'     => null,
                'status'               => 'declined',
                'currency'             => 'TRY',
                'payment_model'        => '3d',
                'installment_count'    => null,
            ],
        ];

        yield '3d_auth_success_payment_fail' => [
            'order'              => [
                'id' => '202306226A90',
            ],
            'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'threeDResponseData' => [
                'CCPrefix'            => '450634',
                'TranType'            => 'Sale',
                'Amount'              => '101',
                'OrderId'             => '00000000202306226A90',
                'MerchantId'          => '6702640212',
                'CAVV'                => 'AAIBAACZZAAAAABllJFzdQAAAAA=',
                'CAVVAlgorithm'       => null,
                'ECI'                 => '05',
                'MD'                  => '0161010063138203939',
                'MdErrorMessage'      => 'Y-status/Challenge authentication via ACS: https://certemvacs.bkm.com.tr/acs/creq',
                'MdStatus'            => '1',
                'SecureTransactionId' => '1010063138203939',
                'Mac'                 => 'aw2jry3dZbmDMvIfuyx3sixxY50ysnRhaR3kOXHLJRw=',
                'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
            ],
            'paymentData'        => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '0148',
                    'ResponseDescription' => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
                'PointDataList'       => null,
                'InstallmentData'     => null,
                'MessageData'         => null,
            ],
            'expectedData'       => [
                'transaction_id'       => null,
                'transaction_time'     => null,
                'transaction_type'     => 'pay',
                'transaction_security' => 'Full 3D Secure',
                'masked_number'        => '450634',
                'md_status'            => '1',
                'md_error_message'     => null,
                'amount'               => 1.01,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'status_detail'        => null,
                'error_code'           => '0148',
                'error_message'        => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                'order_id'             => '202306226A90',
                'remote_order_id'      => '00000000202306226A90',
                'proc_return_code'     => '0148',
                'status'               => 'declined',
                'currency'             => null,
                'payment_model'        => '3d',
                'installment_count'    => null,
            ],
        ];
    }


    public static function mapStatusResponseDataProvider(): iterable
    {
        yield 'success_refunded' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '0000',
                    'ResponseDescription' => 'Başarılı',
                ],
                'TransactionData'     => [
                    [
                        'Amount'            => '1,75',
                        'AuthCode'          => '628698',
                        'CardNo'            => '540061******4581',
                        'CurrencyCode'      => 'TL',
                        'HostLogKey'        => '022562869890000191',
                        'OrderId'           => 'ALB_TST_19091900_20a1234',
                        'TransactionDate'   => '2019-11-0813:58:37.909',
                        'TransactionStatus' => '1',
                        'TransactionType'   => 'Return',
                    ],
                    [
                        'Amount'            => '1,75',
                        'AuthCode'          => '190742',
                        'CardNo'            => '540061******4581',
                        'CurrencyCode'      => 'TL',
                        'HostLogKey'        => '021419074290000191',
                        'OrderId'           => 'ALB_TST_19091900_20a1234',
                        'TransactionDate'   => '2019-09-2018:50:58.111',
                        'TransactionStatus' => '1',
                        'TransactionType'   => 'Sale',
                    ],
                ],
            ],
            'expected' => [
                'order_id'          => 'ALB_TST_19091900_20a1234',
                'auth_code'         => null,
                'transaction_id'    => null,
                'ref_ret_num'       => null,
                'proc_return_code'  => '0000',
                'status'            => 'approved',
                'order_status'      => 'FULLY_REFUNDED',
                'status_detail'     => null,
                'error_code'        => null,
                'error_message'     => null,
                'transaction_time'  => new \DateTimeImmutable('2019-11-0813:58:37.909'),
                'capture_time'      => null,
                'capture'           => null,
                'capture_amount'    => null,
                'transaction_type'  => 'refund',
                'currency'          => 'TRY',
                'first_amount'      => 1.75,
                'masked_number'     => '540061******4581',
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => new \DateTimeImmutable('2019-11-0813:58:37.909'),
                'installment_count' => null,
            ],
        ];
        yield 'fail1' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => 'E219',
                    'ResponseDescription' => 'Kayıt Bulunamadı',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
                'TransactionData'     => null,
            ],
            'expected' => [
                'auth_code'         => null,
                'transaction_id'    => null,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'E219',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'E219',
                'error_message'     => 'Kayıt Bulunamadı',
                'transaction_time'  => null,
                'capture_time'      => null,
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => null,
                'first_amount'      => null,
                'masked_number'     => null,
                'order_id'          => null,
                'order_status'      => null,
                'transaction_type'  => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];
    }

    public static function mapCancelResponseDataProvider(): iterable
    {
        yield 'success1' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '00',
                    'ResponseDescription' => 'Onaylandı',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
            ],
            'expected' => [
                'auth_code'        => null,
                'transaction_id'   => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'transaction_type' => null,
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
            ],
        ];

        yield 'fail1' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '0148',
                    'ResponseDescription' => 'INVALID MID TID IP. Hatalı IP:92.38.180.64',
                ],
            ],
            'expected' => [
                'auth_code'        => null,
                'transaction_id'   => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'transaction_type' => null,
                'proc_return_code' => '0148',
                'status'           => 'declined',
                'status_detail'    => null,
                'error_code'       => '0148',
                'error_message'    => 'INVALID MID TID IP. Hatalı IP:92.38.180.64',
            ],
        ];
    }

    public static function mapRefundResponseDataProvider(): iterable
    {
        yield 'success1' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '00',
                    'ResponseDescription' => 'Onaylandı',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
            ],
            'expected' => [
                'auth_code'        => null,
                'transaction_id'   => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'transaction_type' => null,
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
            ],
        ];

        yield 'fail1' => [
            'response' => [
                'ServiceResponseData' => [
                    'ResponseCode'        => '0148',
                    'ResponseDescription' => 'INVALID MID TID IP. Hatalı IP:92.38.180.64',
                ],
                'AuthCode'            => null,
                'ReferenceCode'       => null,
                'PointDataList'       => null,
                'InstallmentData'     => null,
                'LateChargeData'      => null,
            ],
            'expected' => [
                'auth_code'        => null,
                'transaction_id'   => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'transaction_type' => null,
                'proc_return_code' => '0148',
                'status'           => 'declined',
                'status_detail'    => null,
                'error_code'       => '0148',
                'error_message'    => 'INVALID MID TID IP. Hatalı IP:92.38.180.64',
            ],
        ];
    }
}
