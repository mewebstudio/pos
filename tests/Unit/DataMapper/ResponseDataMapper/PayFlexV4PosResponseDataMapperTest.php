<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class PayFlexV4PosResponseDataMapperTest extends TestCase
{
    private PayFlexV4PosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new PayFlexV4PosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class)
        );
        $this->responseDataMapper = new PayFlexV4PosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["A", false]
     * ["Y", true]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"Status": "Y"}, "Y"]
     * [{"Status": "Y"}, "Y"]
     *
     */
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $actual = $this->responseDataMapper->extractMdStatus($responseData);
        $this->assertSame($expected, $actual);
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
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
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
     * @dataProvider paymentDataProvider
     */
    public function testMapPaymentResponse(string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, []);
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
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
     * @dataProvider cancelDataProvider
     */
    public function testMapCancelResponse(array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($paymentResponse);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testMapRefundResponse(array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($paymentResponse);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
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

    public static function statusTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
                'ResponseInfo' => [
                    'Status'           => 'Error',
                    'ResponseCode'     => '9065',
                    'ResponseMessage'  => 'Üye isyeri bulunamadi',
                    'ResponseDateTime' => '2023-05-27T13:58:53.0340773+03:00',
                    'IsIdempotent'     => 'false',
                ],
            ],
            'expectedData' => [
                'order_id'          => null,
                'auth_code'         => null,
                'proc_return_code'  => '9065',
                'transaction_id'    => null,
                'transaction_type'  => null,
                'ref_ret_num'       => null,
                'order_status'      => null,
                'first_amount'      => null,
                'capture_amount'    => null,
                'currency'          => null,
                'status'            => 'declined',
                'status_detail'     => 'invalid_credentials',
                'error_code'        => '9065',
                'error_message'     => 'Üye isyeri bulunamadi',
                'masked_number'     => null,
                'capture'           => null,
                'capture_time'      => null,
                'transaction_time'  => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        yield 'success_pay' => [
            'responseData' => [
                'ResponseInfo'                => [
                    'Status'           => 'Success',
                    'ResponseCode'     => '0000',
                    'ResponseMessage'  => 'Succeeded.',
                    'ResponseDateTime' => '2023-05-27T13:58:53.0340773+03:00',
                    'IsIdempotent'     => 'false',
                ],
                'PageIndex'                   => 1,
                'PageSize'                    => 10,
                'TotalItemCount'              => 1,
                'TransactionSearchResultInfo' => [
                    'TransactionSearchResultInfo' => [
                        'MerchantId'       => '655500056',
                        'TransactionType'  => 'Sale',
                        'TransactionId'    => 'b2d71cc5-d242-4b01-8479-d56eb8f74d7c',
                        'OrderId'          => 'z2d71cc5-d242-4b01-8479-d56eb8f74d7',
                        'ResultCode'       => '0000',
                        'ResponseMessage'  => 'İŞLEM BAŞARILI',
                        'HostResultCode'   => '000',
                        'AuthCode'         => '11234',
                        'HostDate'         => '1130145930',
                        'Rrn'              => '201101240006',
                        'CurrencyAmount'   => '90.50',
                        'AmountCode'       => '949',
                        'CurrencyCode'     => '',
                        'ThreeDSecureType' => '1',
                        'GainedPoint'      => '0',
                        'TotalPoint'       => '100',
                        'SurchargeAmount'  => '92.50',
                        'Extract'          => '090020304',
                        'IsReversed'       => 'false',
                        'IsCanceled'       => 'false',
                        'IsRefunded'       => 'false',
                        'CustomItems'      => [
                            'CustomItem' => [
                                'Name'  => 'Kontrol1',
                                'Value' => '01',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'          => 'z2d71cc5-d242-4b01-8479-d56eb8f74d7',
                'auth_code'         => '11234',
                'proc_return_code'  => '0000',
                'transaction_id'    => 'b2d71cc5-d242-4b01-8479-d56eb8f74d7c',
                'ref_ret_num'       => '201101240006',
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'pay',
                'first_amount'      => 90.50,
                'capture_amount'    => null,
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'masked_number'     => null,
                'capture'           => null,
                'capture_time'      => null,
                'transaction_time'  => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];
    }

    public static function refundDataProvider(): iterable
    {
        yield 'success_1' => [
            'responseData' => [
                'MerchantId'             => '000100000013506',
                'TransactionType'        => 'Refund',
                'TransactionId'          => '455ae6c09140434ea6edafc0018acbb9',
                'ReferenceTransactionId' => '6d491ea480564068976fafc0018a9def',
                'ResultCode'             => '0000',
                'ResultDetail'           => 'İŞLEM BAŞARILI',
                'InstallmentTable'       => null,
                'CampaignResult'         => null,
                'AuthCode'               => '752800',
                'HostDate'               => '20230309235724',
                'Rrn'                    => '306823971382',
                'TerminalNo'             => 'VP000579',
                'CurrencyAmount'         => '1.01',
                'CurrencyCode'           => '949',
                'BatchNo'                => '300',
                'TLAmount'               => '1.01',
            ],
            'expectedData' => [
                'order_id'         => '455ae6c09140434ea6edafc0018acbb9',
                'auth_code'        => '752800',
                'ref_ret_num'      => '306823971382',
                'proc_return_code' => '0000',
                'transaction_id'   => '455ae6c09140434ea6edafc0018acbb9',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => 'approved',
            ],
        ];

        yield 'fail_1' => [
            'responseData' => [
                'ResultCode'       => '1059',
                'ResultDetail'     => 'İşlemin tamamı iade edilmiş.',
                'InstallmentTable' => null,
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => '1059',
                'transaction_id'   => null,
                'error_code'       => '1059',
                'error_message'    => 'İşlemin tamamı iade edilmiş.',
                'status'           => 'declined',
                'status_detail'    => 'invalid_transaction',
            ],
        ];
    }

    public static function cancelDataProvider(): iterable
    {
        yield 'success_1' => [
            'responseData' => [
                'MerchantId'             => '000100000013506',
                'TransactionType'        => 'Cancel',
                'TransactionId'          => '4a8e979308de4568b500afc00187a501',
                'ReferenceTransactionId' => '3f30ab117aa74826b448afc0018789fa',
                'ResultCode'             => '0000',
                'ResultDetail'           => 'İŞLEM BAŞARILI',
                'InstallmentTable'       => null,
                'CampaignResult'         => null,
                'AuthCode'               => '836044',
                'HostDate'               => '20230309234556',
                'Rrn'                    => '306823971363',
                'TerminalNo'             => 'VP000579',
                'CurrencyAmount'         => '1.01',
                'CurrencyCode'           => '949',
                'BatchNo'                => '300',
                'TLAmount'               => '1.01',
            ],
            'expectedData' => [
                'order_id'         => '4a8e979308de4568b500afc00187a501',
                'auth_code'        => '836044',
                'ref_ret_num'      => '306823971363',
                'proc_return_code' => '0000',
                'transaction_id'   => '4a8e979308de4568b500afc00187a501',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => 'approved',
            ],
        ];

        yield 'fail_1' => [
            'responseData' => [
                'ResultCode'       => '1083',
                'ResultDetail'     => 'Referans islem daha önceden iptal edilmis.',
                'InstallmentTable' => null,
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => '1083',
                'transaction_id'   => null,
                'error_code'       => '1083',
                'error_message'    => 'Referans islem daha önceden iptal edilmis.',
                'status'           => 'declined',
                'status_detail'    => 'invalid_transaction',
            ],
        ];
    }

    public static function paymentDataProvider(): iterable
    {
        yield 'success_1' => [
            'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'responseData' => [
                'MerchantId'              => '000100000013506',
                'TransactionType'         => 'Sale',
                'TransactionId'           => '9972767117b3400eb2acafc0018643df',
                'ResultCode'              => '0000',
                'ResultDetail'            => 'İŞLEM BAŞARILI',
                'CustomItems'             => [
                    'Item' => [
                        '@name'  => 'CardHolderName',
                        '@value' => 'AR* ÖZ*',
                        '#'      => null,
                    ],
                ],
                'InstallmentTable'        => null,
                'CampaignResult'          => null,
                'AuthCode'                => '961451',
                'HostDate'                => '20230309234054',
                'Rrn'                     => '306823971358',
                'TerminalNo'              => 'VP000579',
                'GainedPoint'             => '10.00',
                'TotalPoint'              => '103032.52',
                'CurrencyAmount'          => '1.01',
                'CurrencyCode'            => '949',
                'OrderId'                 => '202303095646',
                'ThreeDSecureType'        => '1',
                'TransactionDeviceSource' => '0',
                'BatchNo'                 => '300',
                'TLAmount'                => '1.01',
            ],
            'expectedData' => [
                'transaction_id'    => '9972767117b3400eb2acafc0018643df',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2023-03-09 23:40:54'),
                'auth_code'         => '961451',
                'ref_ret_num'       => '9972767117b3400eb2acafc0018643df',
                'batch_num'         => '300',
                'order_id'          => '202303095646',
                'proc_return_code'  => '0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'payment_model'     => 'regular',
                'installment_count' => null,
            ],
        ];

        yield 'success_with_short_host_date' => [
            'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'responseData' => [
                'MerchantId'              => '000100000013506',
                'TransactionType'         => 'Sale',
                'TransactionId'           => '9972767117b3400eb2acafc0018643df',
                'ResultCode'              => '0000',
                'ResultDetail'            => 'İŞLEM BAŞARILI',
                'CustomItems'             => [
                    'Item' => [
                        '@name'  => 'CardHolderName',
                        '@value' => 'AR* ÖZ*',
                        '#'      => null,
                    ],
                ],
                'InstallmentTable'        => null,
                'CampaignResult'          => null,
                'AuthCode'                => '961451',
                'HostDate'                => '0309234054',
                'Rrn'                     => '306823971358',
                'TerminalNo'              => 'VP000579',
                'GainedPoint'             => '10.00',
                'TotalPoint'              => '103032.52',
                'CurrencyAmount'          => '1.01',
                'CurrencyCode'            => '949',
                'OrderId'                 => '202303095646',
                'ThreeDSecureType'        => '1',
                'TransactionDeviceSource' => '0',
                'BatchNo'                 => '300',
                'TLAmount'                => '1.01',
            ],
            'expectedData' => [
                'transaction_id'    => '9972767117b3400eb2acafc0018643df',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(date('Y').'-03-09 23:40:54'),
                'auth_code'         => '961451',
                'ref_ret_num'       => '9972767117b3400eb2acafc0018643df',
                'batch_num'         => '300',
                'order_id'          => '202303095646',
                'proc_return_code'  => '0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'payment_model'     => 'regular',
                'installment_count' => null,
            ],
        ];

        yield 'fail_1' => [
            'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'responseData' => [
                'MerchantId'              => '000100000013506',
                'TransactionType'         => 'Sale',
                'TransactionId'           => '9b47227c275246d39454afc00186dfba',
                'ResultCode'              => '0312',
                'ResultDetail'            => 'RED-GEÇERSİZ KART',
                'InstallmentTable'        => null,
                'CampaignResult'          => null,
                'AuthCode'                => '000000',
                'HostDate'                => '20230309234307',
                'Rrn'                     => '306823971359',
                'TerminalNo'              => 'VP000579',
                'CurrencyAmount'          => '1.01',
                'CurrencyCode'            => '949',
                'OrderId'                 => '20230309EF68',
                'ThreeDSecureType'        => '1',
                'TransactionDeviceSource' => '0',
                'BatchNo'                 => '300',
                'TLAmount'                => '1.01',
            ],
            'expectedData' => [
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'order_id'          => '20230309EF68',
                'proc_return_code'  => '0312',
                'status'            => 'declined',
                'status_detail'     => 'reject',
                'error_code'        => '0312',
                'error_message'     => 'RED-GEÇERSİZ KART',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'payment_model'     => 'regular',
                'installment_count' => null,
            ],
        ];
        yield 'fail_2' => [
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'ResultCode'       => '9039',
                'ResultDetail'     => 'Üye işyeri bulunamadı.',
                'InstallmentTable' => '',
            ],
            'expectedData' => [
                'transaction_id'    => null,
                'transaction_type'  => null,
                'transaction_time'  => null,
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'order_id'          => null,
                'proc_return_code'  => '9039',
                'status'            => 'declined',
                'status_detail'     => 'invalid_credentials',
                'error_code'        => '9039',
                'error_message'     => 'Üye işyeri bulunamadı.',
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => null,
                'installment_count' => null,
            ],
        ];
    }

    public static function threeDPaymentDataProvider(): array
    {
        return [
            '3d_auth_fail'                 => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'MerchantId'                => '000000000111111',
                    'SubMerchantNo'             => '0',
                    'SubMerchantName'           => null,
                    'SubMerchantNumber'         => null,
                    'PurchAmount'               => 100,
                    'PurchCurrency'             => '949',
                    'VerifyEnrollmentRequestId' => 'order-id-123',
                    'SessionInfo'               => ['data' => 'sss'],
                    'InstallmentCount'          => null,
                    'Pan'                       => '5555444433332222',
                    'Expiry'                    => 'hj',
                    'Xid'                       => 'xid0393i3kdkdlslsls',
                    'Status'                    => 'E', //diger hata durumlari N, U
                    'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'Eci'                       => '02',
                    'ExpSign'                   => '',
                    'ErrorCode'                 => '1105',
                    'ErrorMessage'              => 'Üye isyeri IP si sistemde tanimli degil',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'eci'                  => '02',
                    'cavv'                 => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'md_status'            => 'E',
                    'md_error_message'     => 'Üye isyeri IP si sistemde tanimli degil',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'auth_code'            => null,
                    'order_id'             => 'order-id-123',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => '1105',
                    'error_message'        => 'Üye isyeri IP si sistemde tanimli degil',
                    'amount'               => null,
                    'currency'             => 'TRY',
                    'payment_model'        => null,
                    'installment_count'    => 0,
                ],
            ],
            '3d_auth_success_payment_fail' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'threeDResponseData' => [
                    'MerchantId'                => '000100000013506',
                    'Pan'                       => '4938460158754205',
                    'Expiry'                    => '2411',
                    'PurchAmount'               => '101',
                    'PurchCurrency'             => '949',
                    'VerifyEnrollmentRequestId' => 'ce06048a3e9c0cd1d437803fb38b5ad0',
                    'Xid'                       => 'ondg8d9t5besgt88sk8h',
                    'SessionInfo'               => 'jpf58sdjj8p9mpb9shurh47v64',
                    'Status'                    => 'Y',
                    'Cavv'                      => 'ABIBCBgAAAEnAAABAQAAAAAAAAA=',
                    'Eci'                       => '05',
                    'ExpSign'                   => null,
                    'InstallmentCount'          => null,
                    'SubMerchantNo'             => null,
                    'SubMerchantName'           => null,
                    'SubMerchantNumber'         => null,
                    'ErrorCode'                 => null,
                    'ErrorMessage'              => null,
                ],
                'paymentData'        => [
                    'MerchantId'              => '000100000013506',
                    'TransactionType'         => 'Sale',
                    'TransactionId'           => '202303091489',
                    'ResultCode'              => '0312',
                    'ResultDetail'            => 'RED-GEÇERSİZ KART',
                    'InstallmentTable'        => null,
                    'CampaignResult'          => null,
                    'AuthCode'                => '000000',
                    'HostDate'                => '20230309235359',
                    'Rrn'                     => '306823971380',
                    'TerminalNo'              => 'VP000579',
                    'CurrencyAmount'          => '1.01',
                    'CurrencyCode'            => '949',
                    'OrderId'                 => '202303091489',
                    'ECI'                     => '05',
                    'ThreeDSecureType'        => '2',
                    'TransactionDeviceSource' => '0',
                    'BatchNo'                 => '300',
                    'TLAmount'                => '1.01',
                ],
                'expectedData'       => [
                    'cavv'                 => 'ABIBCBgAAAEnAAABAQAAAAAAAAA=',
                    'md_status'            => 'Y',
                    'md_error_message'     => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '0312',
                    'eci'                  => '05',
                    'auth_code'            => null,
                    'order_id'             => '202303091489',
                    'status'               => 'declined',
                    'status_detail'        => 'reject',
                    'error_code'           => '0312',
                    'error_message'        => 'RED-GEÇERSİZ KART',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
            'success1'                     => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'threeDResponseData' => [
                    'MerchantId'                => '000000000111111',
                    'SubMerchantNo'             => '0',
                    'SubMerchantName'           => null,
                    'SubMerchantNumber'         => null,
                    'PurchAmount'               => 100,
                    'PurchCurrency'             => '949',
                    'VerifyEnrollmentRequestId' => 'order-id-123',
                    'SessionInfo'               => ['data' => 'sss'],
                    'InstallmentCount'          => null,
                    'Pan'                       => '5555444433332222',
                    'Expiry'                    => 'cv',
                    'Xid'                       => 'xid0393i3kdkdlslsls',
                    'Status'                    => 'Y',
                    'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'Eci'                       => '02',
                    'ExpSign'                   => null,
                    'ErrorCode'                 => null,
                    'ErrorMessage'              => null,
                ],
                'paymentData'        => [
                    'MerchantId'              => '000000000111111',
                    'TerminalNo'              => 'VP999999',
                    'TransactionType'         => 'Sale',
                    'TransactionId'           => '20230309B838',
                    'ResultCode'              => '0000',
                    'ResultDetail'            => 'İŞLEM BAŞARILI',
                    'CustomItems'             => [],
                    'InstallmentTable'        => null,
                    'CampaignResult'          => null,
                    'AuthCode'                => '822641',
                    'HostDate'                => '20220404123456',
                    'Rrn'                     => '209411062014',
                    'CurrencyAmount'          => 100,
                    'CurrencyCode'            => '949',
                    'OrderId'                 => 'order-id-123',
                    'TLAmount'                => 100,
                    'ECI'                     => '02',
                    'ThreeDSecureType'        => '2',
                    'TransactionDeviceSource' => '0',
                    'BatchNo'                 => '1',
                ],
                'expectedData'       => [
                    'cavv'                 => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'md_status'            => 'Y',
                    'md_error_message'     => null,
                    'transaction_id'       => '20230309B838',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-04-04 12:34:56'),
                    'transaction_security' => null,
                    'ref_ret_num'          => '20230309B838',
                    'batch_num'            => '1',
                    'proc_return_code'     => '0000',
                    'eci'                  => '02',
                    'auth_code'            => '822641',
                    'order_id'             => 'order-id-123',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'amount'               => 100.0,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
        ];
    }
}
