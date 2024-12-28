<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class EstPosResponseDataMapperTest extends TestCase
{
    private EstPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new EstPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new EstPosResponseDataMapper(
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
     * [{"mdStatus": "1"}, "1"]
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
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order,
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
     * @dataProvider threeDPayPaymentDataProvider
     */
    public function testMap3DPayResponseData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($responseData, $txType, $order);
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
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        if (isset($actualData['recurringOrders'])) {
            foreach ($actualData['recurringOrders'] as $key => $actualRecurringOrder) {
                $expectedRecurringOrder = $expectedData['recurringOrders'][$key];
                \ksort($expectedData['recurringOrders'][$key]);
                \ksort($actualData['recurringOrders'][$key]);
                $this->assertEquals($expectedRecurringOrder['transaction_time'], $actualRecurringOrder['transaction_time']);
                $this->assertEquals($expectedRecurringOrder['capture_time'], $actualRecurringOrder['capture_time']);
                unset($actualData['recurringOrders'][$key]['transaction_time'], $expectedData['recurringOrders'][$key]['transaction_time']);
                unset($actualData['recurringOrders'][$key]['capture_time'], $expectedData['recurringOrders'][$key]['capture_time']);
            }
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
            $this->assertEquals($expectedData['capture_time'], $actualData['capture_time']);
            $this->assertEquals($expectedData['refund_time'], $actualData['refund_time']);
            $this->assertEquals($expectedData['cancel_time'], $actualData['cancel_time']);
            unset($actualData['transaction_time'], $expectedData['transaction_time']);
            unset($actualData['capture_time'], $expectedData['capture_time']);
            unset($actualData['cancel_time'], $expectedData['cancel_time']);
            unset($actualData['refund_time'], $expectedData['refund_time']);
        }

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

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider orderHistoryTestDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapOrderHistoryResponse($responseData);
        if (count($responseData['Extra']) > 0) {
            if (count($actualData['transactions']) > 1
                && null !== $actualData['transactions'][0]['transaction_time']
                && null !== $actualData['transactions'][1]['transaction_time']
            ) {
                $this->assertGreaterThan(
                    $actualData['transactions'][0]['transaction_time'],
                    $actualData['transactions'][1]['transaction_time'],
                );
            }

            foreach (array_keys($actualData['transactions']) as $key) {
                $this->assertEquals($expectedData['transactions'][$key]['transaction_time'], $actualData['transactions'][$key]['transaction_time']);
                $this->assertEquals($expectedData['transactions'][$key]['capture_time'], $actualData['transactions'][$key]['capture_time']);
                unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
                \ksort($actualData['transactions'][$key]);
                \ksort($expectedData['transactions'][$key]);
            }

            $this->assertCount($actualData['trans_count'], $actualData['transactions']);
        }

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
    }

    public static function paymentTestDataProvider(): iterable
    {
        yield 'success1' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'        => '202210293885',
                'GroupId'        => '202210293885',
                'Response'       => 'Approved',
                'AuthCode'       => 'P48911',
                'HostRefNum'     => '230200671758',
                'ProcReturnCode' => '00',
                'TransId'        => '22302V8rE11732',
                'ErrMsg'         => null,
                'Extra'          => [
                    'SETTLEID'           => '2286',
                    'TRXDATE'            => '20221029 21:58:43',
                    'ERRORCODE'          => null,
                    'TERMINALID'         => '00655020',
                    'MERCHANTID'         => '655000200',
                    'CARDBRAND'          => 'VISA',
                    'CARDISSUER'         => 'AKBANK T.A.S.',
                    'AVSAPPROVE'         => 'Y',
                    'HOSTDATE'           => '1029-215844',
                    'AVSERRORCODEDETAIL' => 'avshatali-avshatali-avshatali-avshatali-',
                    'NUMCODE'            => '00',
                ],
            ],
            'expectedData' => [
                'transaction_id'    => '22302V8rE11732',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2022-10-29 21:58:43'),
                'payment_model'     => 'regular',
                'order_id'          => '202210293885',
                'group_id'          => '202210293885',
                'auth_code'         => 'P48911',
                'ref_ret_num'       => '230200671758',
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'installment_count' => null,
            ],
        ];
        yield 'success2WithoutERRORCODE' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'        => '202210293885',
                'GroupId'        => '202210293885',
                'Response'       => 'Approved',
                'AuthCode'       => 'P48911',
                'HostRefNum'     => '230200671758',
                'ProcReturnCode' => '00',
                'TransId'        => '22302V8rE11732',
                'ErrMsg'         => null,
                'Extra'          => [
                    'SETTLEID'           => '2286',
                    'TRXDATE'            => '20221029 21:58:43',
                    'TERMINALID'         => '00655020',
                    'MERCHANTID'         => '655000200',
                    'CARDBRAND'          => 'VISA',
                    'CARDISSUER'         => 'AKBANK T.A.S.',
                    'AVSAPPROVE'         => 'Y',
                    'HOSTDATE'           => '1029-215844',
                    'AVSERRORCODEDETAIL' => 'avshatali-avshatali-avshatali-avshatali-',
                    'NUMCODE'            => '00',
                ],
            ],
            'expectedData' => [
                'transaction_id'    => '22302V8rE11732',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2022-10-29 21:58:43'),
                'payment_model'     => 'regular',
                'order_id'          => '202210293885',
                'group_id'          => '202210293885',
                'auth_code'         => 'P48911',
                'ref_ret_num'       => '230200671758',
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'installment_count' => null,
            ],
        ];
        yield 'fail1' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'        => '20221029B541',
                'GroupId'        => '20221029B541',
                'Response'       => 'Error',
                'AuthCode'       => '',
                'HostRefNum'     => '',
                'ProcReturnCode' => '99',
                'TransId'        => '22302WcCC13836',
                'ErrMsg'         => 'Kredi karti numarasi gecerli formatta degil.',
                'Extra'          => [
                    'SETTLEID'  => '',
                    'TRXDATE'   => '20221029 22:28:01',
                    'ERRORCODE' => 'CORE-2012',
                    'NUMCODE'   => '992012',
                ],
            ],
            'expectedData' => [
                'transaction_id'    => '22302WcCC13836',
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'payment_model'     => 'regular',
                'order_id'          => '20221029B541',
                'group_id'          => '20221029B541',
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => '99',
                'status'            => 'declined',
                'status_detail'     => 'general_error',
                'error_code'        => 'CORE-2012',
                'error_message'     => 'Kredi karti numarasi gecerli formatta degil.',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'installment_count' => null,
            ],
        ];

        yield 'success_2' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'OrderId'        => '20221030FAC5',
                'GroupId'        => '20221030FAC5',
                'Response'       => 'Approved',
                'AuthCode'       => 'P90325',
                'HostRefNum'     => '230300671782',
                'ProcReturnCode' => '00',
                'TransId'        => '22303Md4C19254',
                'ErrMsg'         => '',
                'Extra'          => [
                    'SETTLEID'           => '2287',
                    'TRXDATE'            => '20221030 12:29:53',
                    'ERRORCODE'          => '',
                    'TERMINALID'         => '00655020',
                    'MERCHANTID'         => '655000200',
                    'CARDBRAND'          => 'VISA',
                    'CARDISSUER'         => 'AKBANK T.A.S.',
                    'AVSAPPROVE'         => 'Y',
                    'HOSTDATE'           => '1030-122954',
                    'AVSERRORCODEDETAIL' => 'avshatali-avshatali-avshatali-avshatali-',
                    'NUMCODE'            => '00',
                ],
            ],
            'expectedData' => [
                'transaction_id'    => '22303Md4C19254',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2022-10-30 12:29:53'),
                'payment_model'     => 'regular',
                'order_id'          => '20221030FAC5',
                'group_id'          => '20221030FAC5',
                'auth_code'         => 'P90325',
                'ref_ret_num'       => '230300671782',
                'batch_num'         => null,
                'proc_return_code'  => '00',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'installment_count' => null,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            '3d_auth_fail'                          => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'sID'                             => '1',
                    'oid'                             => '2022103076E7',
                    'encoding'                        => 'ISO-8859-9',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'version'                         => '2.0',
                    'currency'                        => '949',
                    'dsId'                            => '1',
                    'callbackCall'                    => 'true',
                    'amount'                          => '1.01',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'islemtipi'                       => 'Auth',
                    'firmaadi'                        => 'John Doe',
                    'merchantName'                    => 'Ziraat 3D',
                    'ACQBIN'                          => '454672',
                    'PAResSyntaxOK'                   => 'true',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'storetype'                       => '3d',
                    'mdStatus'                        => '0',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'clientIp'                        => '89.244.149.137',
                    'merchantID'                      => '190100000',
                    'mdErrorMsg'                      => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704671.1667119085._nUCBN9o1Wh',
                    'clientid'                        => '190100000',
                    'MaskedPan'                       => '435508***4358',
                    'txstatus'                        => 'N',
                    'digest'                          => 'digest',
                    'PAResVerified'                   => 'true',
                    'Email'                           => 'mail@customer.com',
                    'taksit'                          => '',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:72240E12F06488A0D50ECB1AF842B5E939950C417D6456EA033087ED8E7FA6CE:3894:##190100000',
                    'lang'                            => 'tr',
                    'xid'                             => 'rIpI0Jrjzra7OF6UyD4pQZVyxpw=',
                    'TRANID'                          => '',
                    'HASH'                            => 'gEcAQuPX+Wriv+UJ+mNCYouzV04=',
                    'rnd'                             => 'THlysKkGRD/Ly/z5xSKB',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '1901000002022103076E70435508:72240E12F06488A0D50ECB1AF842B5E939950C417D6456EA033087ED8E7FA6CE:3894:##190100000THlysKkGRD/Ly/z5xSKB',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => null,
                    'tx_status'            => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704671.1667119085._nUCBN9o1Wh',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '2022103076E7',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
            '3d_auth_fail_wrong_card_number_format' => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'amount'                          => 0.01,
                    'clientid'                        => '*',
                    'currency'                        => '*',
                    'Ecom_Payment_Card_ExpDate_Month' => 0,
                    'Ecom_Payment_Card_ExpDate_Year'  => 0,
                    'ErrMsg'                          => 'Girilen kart numarası doğru formatta değildir. Kart numarasını kontrol ederek tekrar işlem deneyiniz.',
                    'ErrorCode'                       => 'HPP-1001',
                    'failUrl'                         => 'https://*.com/odeme/f05e81c8-4ea0-44a9-8fe8-d45b854c62d9',
                    'HASH'                            => '**/fxNKZvC4E2EbQOgiqNi9FeXBMj636Q==',
                    'hashAlgorithm'                   => 'ver3',
                    'lang'                            => 'tr',
                    'maskedCreditCard'                => '***',
                    'MaskedPan'                       => '**',
                    'oid'                             => 'f05e81c8',
                    'okUrl'                           => 'https://*.com/odeme/d45b854c62d9',
                    'Response'                        => 'Error',
                    'rnd'                             => 'MZrcwoSd1+-*',
                    'storetype'                       => '3d',
                    'taksit'                          => '',
                    'traceId'                         => '****',
                    'TranType'                        => 'Auth',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'amount'               => 0.01,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'cavv'                 => null,
                    'currency'             => null,
                    'eci'                  => null,
                    'error_code'           => 'HPP-1001',
                    'error_message'        => 'Girilen kart numarası doğru formatta değildir. Kart numarasını kontrol ederek tekrar işlem deneyiniz.',
                    'installment_count'    => 0,
                    'masked_number'        => '***',
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'month'                => 0,
                    'order_id'             => 'f05e81c8',
                    'payment_model'        => '3d',
                    'proc_return_code'     => null,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_security' => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'tx_status'            => null,
                    'year'                 => 0,
                ],
            ],
            '3d_auth_success_payment_fail'          => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'TRANID'                          => '',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'lang'                            => 'tr',
                    'merchantID'                      => '190100000',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '454672',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'merchantName'                    => 'Ziraat 3D',
                    'clientIp'                        => '89.244.149.137',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:9716234382F9D9B630CC01452A6F160D31A2E1DBD41706C6AF8B8E6F730FE65D:3677:##190100000',
                    'taksit'                          => '12',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704701.1667119495.sIHzA7ckv-0',
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABA##################AEJI=',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'xid'                             => '2aeoSfQde3NyV2XjSeTL0sGNYSg=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '20221030FE4C',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'clientid'                        => '190100000',
                    'txstatus'                        => 'Y',
                    'HASH'                            => '+NYQKADaaWWUIAIg6U77nGIK+8k=',
                    'rnd'                             => 'IXa1XnlaOxpMCacqG/cB',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '19010000020221030FE4C1ABABA##################AEJI=05435508:9716234382F9D9B630CC01452A6F160D31A2E1DBD41706C6AF8B8E6F730FE65D:3677:##190100000IXa1XnlaOxpMCacqG/cB',
                ],
                'paymentData'        => [
                    'OrderId'        => '20221030FE4C',
                    'GroupId'        => '20221030FE4C',
                    'Response'       => 'Error',
                    'AuthCode'       => '',
                    'HostRefNum'     => '',
                    'ProcReturnCode' => '99',
                    'TransId'        => '22303LtCH15933',
                    'ErrMsg'         => 'Taksit tablosu icin gecersiz deger',
                    'Extra'          => [
                        'SETTLEID'  => '',
                        'TRXDATE'   => '20221030 11:45:02',
                        'ERRORCODE' => 'CORE-2603',
                        'NUMCODE'   => '992603',
                    ],
                ],
                'expectedData'       => [
                    'transaction_id'       => '22303LtCH15933',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => '05',
                    'tx_status'            => null,
                    'cavv'                 => 'ABABA##################AEJI=',
                    'md_error_message'     => null,
                    'group_id'             => '20221030FE4C',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '99',
                    'status'               => 'declined',
                    'status_detail'        => 'general_error',
                    'error_code'           => 'CORE-2603',
                    'error_message'        => 'Taksit tablosu icin gecersiz deger',
                    'recurring_id'         => null,
                    'installment_count'    => 12,
                    'order_id'             => '20221030FE4C',
                    'payment_model'        => '3d',
                ],
            ],
            '3d_auth_success_payment_fail_wong_cvv' => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'TRANID'                          => '',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'lang'                            => 'tr',
                    'merchantID'                      => '190100000',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '454672',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'merchantName'                    => 'Ziraat 3D',
                    'clientIp'                        => '89.244.149.137',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:9716234382F9D9B630CC01452A6F160D31A2E1DBD41706C6AF8B8E6F730FE65D:3677:##190100000',
                    'taksit'                          => '12',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704701.1667119495.sIHzA7ckv-0',
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABA##################AEJI=',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'xid'                             => '2aeoSfQde3NyV2XjSeTL0sGNYSg=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '20221030FE4C',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'clientid'                        => '190100000',
                    'txstatus'                        => 'Y',
                    'HASH'                            => '+NYQKADaaWWUIAIg6U77nGIK+8k=',
                    'rnd'                             => 'IXa1XnlaOxpMCacqG/cB',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '19010000020221030FE4C1ABABA##################AEJI=05435508:9716234382F9D9B630CC01452A6F160D31A2E1DBD41706C6AF8B8E6F730FE65D:3677:##190100000IXa1XnlaOxpMCacqG/cB',
                ],
                'paymentData'        => [
                    'OrderId'        => '4c4',
                    'GroupId'        => '4aa841c4',
                    'Response'       => 'Declined',
                    'HostRefNum'     => '4139489',
                    'ProcReturnCode' => '82',
                    'TransId'        => '24138rt2596',
                    'ErrMsg'         => 'CVV Hatasi veya girilen CVV gecersiz.',
                    'Extra'          => [
                        'KULLANILANPUAN'     => '000000000000',
                        'CARDBRAND'          => 'VISA',
                        'CARDISSUER'         => 'ZİRAAT BANKASI',
                        'ERRORCODE'          => 'ISO8583-82',
                        'TRXDATE'            => '20240517 13:17:31',
                        'KULLANILABILIRPUAN' => '000000000000',
                        'ACQSTAN'            => '769489',
                        'KAZANILANPUAN'      => '000000000105',
                        'TRACEID'            => '2c4e0abd4560418ace038267fa57f5c9',
                        'NUMCODE'            => '82',
                    ],
                ],
                'expectedData'       => [
                    'transaction_id'       => '24138rt2596',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => '05',
                    'tx_status'            => null,
                    'cavv'                 => 'ABABA##################AEJI=',
                    'md_error_message'     => null,
                    'group_id'             => '4aa841c4',
                    'auth_code'            => null,
                    'ref_ret_num'          => '4139489',
                    'batch_num'            => null,
                    'proc_return_code'     => '82',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => 'ISO8583-82',
                    'error_message'        => 'CVV Hatasi veya girilen CVV gecersiz.',
                    'recurring_id'         => null,
                    'installment_count'    => 12,
                    'order_id'             => '4c4',
                    'payment_model'        => '3d',
                ],
            ],
            'success1'                              => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'TRANID'                          => '',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'lang'                            => 'tr',
                    'merchantID'                      => '190100000',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '454672',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'merchantName'                    => 'Ziraat 3D',
                    'clientIp'                        => '89.244.149.137',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:4328956B2D668C558B0AECFF49A883EEFF2CD4168F54758441F31C79840636B8:3827:##190100000',
                    'taksit'                          => '',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704801.1667119895.nGaNF3vG_bb',
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABCSQDGQAAAABllJMDdUQAEJI=',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'xid'                             => 'XWOb78QRZ1Re8f7i8b2ZW85cLr8=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '202210304547',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'clientid'                        => '190100000',
                    'txstatus'                        => 'Y',
                    'HASH'                            => 'MyL35j/zA22D90nUkW0on1k2njE=',
                    'rnd'                             => 'yVCgrF4/9i3p9R0rQvw8',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '1901000002022103045471ABABCSQDGQAAAABllJMDdUQAEJI=05435508:4328956B2D668C558B0AECFF49A883EEFF2CD4168F54758441F31C79840636B8:3827:##190100000yVCgrF4/9i3p9R0rQvw8',
                ],
                'paymentData'        => [
                    'OrderId'        => '202210304547',
                    'GroupId'        => '202210304547',
                    'Response'       => 'Approved',
                    'AuthCode'       => '563339',
                    'HostRefNum'     => '230311184777',
                    'ProcReturnCode' => '00',
                    'TransId'        => '22303LzpJ16296',
                    'ErrMsg'         => '',
                    'Extra'          => [
                        'SETTLEID'      => '2400',
                        'TRXDATE'       => '20221030 11:51:41',
                        'ERRORCODE'     => '',
                        'CARDBRAND'     => 'VISA',
                        'CARDISSUER'    => 'AKBANK T.A.S.',
                        'KAZANILANPUAN' => '000000010.00',
                        'NUMCODE'       => '00',
                    ],
                ],
                'expectedData'       => [
                    'transaction_id'       => '22303LzpJ16296',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-10-30 11:51:41'),
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => '05',
                    'tx_status'            => null,
                    'cavv'                 => 'ABABCSQDGQAAAABllJMDdUQAEJI=',
                    'md_error_message'     => null,
                    'group_id'             => '202210304547',
                    'auth_code'            => '563339',
                    'ref_ret_num'          => '230311184777',
                    'batch_num'            => null,
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'recurring_id'         => null,
                    'installment_count'    => 0,
                    'order_id'             => '202210304547',
                    'payment_model'        => '3d',
                ],
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
                    'ReturnOid'                       => '2022103030CB',
                    'TRANID'                          => '',
                    'EXTRA_MERCHANTID'                => '655000200',
                    'PAResSyntaxOK'                   => 'true',
                    'EXTRA_HOSTDATE'                  => '1030-112244',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'EXTRA_TERMINALID'                => '00655020',
                    'lang'                            => 'tr',
                    'merchantID'                      => '700655000200',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '406456',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'EXTRA_CARDBRAND'                 => 'VISA',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'acqStan'                         => '671764',
                    'merchantName'                    => 'İşbank 3d_pay Store',
                    'clientIp'                        => '89.244.149.137',
                    'okUrl'                           => 'http://localhost/akbank/3d-pay/response.php',
                    'md'                              => '435508:EC9CDC37975501A4B29BBD5BE1580279238BF88D888B23E7ECC293581C75EE40:4333:##700655000200',
                    'ProcReturnCode'                  => '00',
                    'payResults_dsId'                 => '1',
                    'taksit'                          => '',
                    'TransId'                         => '22303LWsA14386',
                    'EXTRA_TRXDATE'                   => '20221030 11:22:43',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d_pay',
                    'Response'                        => 'Approved',
                    'SettleId'                        => '2287',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704511.1667118159.BUW_iXHm4_6',
                    'ErrMsg'                          => '',
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABByBkEgAAAABllJMDdVWUGZE=',
                    'EXTRA_AVSERRORCODEDETAIL'        => 'avshatali-avshatali-avshatali-avshatali-',
                    'digest'                          => 'digest',
                    'HostRefNum'                      => '230300671764',
                    'callbackCall'                    => 'true',
                    'AuthCode'                        => 'P37891',
                    'failUrl'                         => 'http://localhost/akbank/3d-pay/response.php',
                    'xid'                             => 'xyxZZ/eJ3eVDkqYiDOdwPfCkq5U=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '2022103030CB',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'EXTRA_AVSAPPROVE'                => 'Y',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'EXTRA_CARDISSUER'                => 'AKBANK T.A.S.',
                    'clientid'                        => '700655000200',
                    'txstatus'                        => 'Y',
                    'HASH'                            => 'FQLnGOxBBMIoMIRxehiaLtkEd34=',
                    'rnd'                             => 'kP/2JB5ajHJt+yVhHNG9',
                    'HASHPARAMS'                      => 'clientid:oid:AuthCode:ProcReturnCode:Response:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '7006550002002022103030CBP3789100Approved1ABABByBkEgAAAABllJMDdVWUGZE=05435508:EC9CDC37975501A4B29BBD5BE1580279238BF88D888B23E7ECC293581C75EE40:4333:##700655000200kP/2JB5ajHJt+yVhHNG9',
                    'NATIONALIDNO'                    => '',
                ],
                'expectedData' => [
                    'transaction_id'       => '22303LWsA14386',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-10-30 11:22:43'),
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'eci'                  => '05',
                    'cavv'                 => 'ABABByBkEgAAAABllJMDdVWUGZE=',
                    'md_error_message'     => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704511.1667118159.BUW_iXHm4_6',
                    'order_id'             => '2022103030CB',
                    'auth_code'            => 'P37891',
                    'ref_ret_num'          => '230300671764',
                    'batch_num'            => null,
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'payment_model'        => '3d_pay',
                    'installment_count'    => 0,
                ],
            ],
            'authFail1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'sID'                             => '1',
                    'oid'                             => '2022103008A3',
                    'encoding'                        => 'ISO-8859-9',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'version'                         => '2.0',
                    'currency'                        => '949',
                    'dsId'                            => '1',
                    'callbackCall'                    => 'true',
                    'amount'                          => '1.01',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'islemtipi'                       => 'Auth',
                    'firmaadi'                        => 'John Doe',
                    'merchantName'                    => 'İşbank 3d_pay Store',
                    'ACQBIN'                          => '406456',
                    'PAResSyntaxOK'                   => 'true',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'storetype'                       => '3d_pay',
                    'mdStatus'                        => '0',
                    'failUrl'                         => 'http://localhost/akbank/3d-pay/response.php',
                    'clientIp'                        => '89.244.149.137',
                    'merchantID'                      => '700655000200',
                    'mdErrorMsg'                      => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704541.1667118445.QQ1EjzXz8nm',
                    'clientid'                        => '700655000200',
                    'MaskedPan'                       => '435508***4358',
                    'txstatus'                        => 'N',
                    'digest'                          => 'digest',
                    'PAResVerified'                   => 'true',
                    'Email'                           => 'mail@customer.com',
                    'taksit'                          => '',
                    'okUrl'                           => 'http://localhost/akbank/3d-pay/response.php',
                    'md'                              => '435508:44868DF53C03B6FFC4479AF5C897CC86F10D7D3D6C20859EA77277B0E954125F:4320:##700655000200',
                    'lang'                            => 'tr',
                    'xid'                             => 'jDiMogllA6etX+EvmM+zG+VMvo4=',
                    'TRANID'                          => '',
                    'HASH'                            => 'mbWDXpM1SQfYIEJ5M1KfP/hOE18=',
                    'rnd'                             => 'I6wQZkKfEnDG1myeLBlt',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '7006550002002022103008A30435508:44868DF53C03B6FFC4479AF5C897CC86F10D7D3D6C20859EA77277B0E954125F:4320:##700655000200I6wQZkKfEnDG1myeLBlt',
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
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => '0',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'eci'                  => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704541.1667118445.QQ1EjzXz8nm',
                    'order_id'             => '2022103008A3',
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'payment_model'        => '3d_pay',
                    'installment_count'    => 0,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1'      => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'panFirst6'                       => '',
                    'TRANID'                          => '',
                    'tadres2'                         => '',
                    'SECMELIKAMPANYAKOD'              => '000001',
                    'PAResSyntaxOK'                   => 'true',
                    'querydcchash'                    => 'ibQg/1ukwQq0I713SvZmfOpsWemylDZj+CJAYfSYn1aHbkycJ2HWJCVYUNcuZWOV7SGHaYp6cHAc9/dZq4wahA==',
                    'panLast4'                        => '',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'campaignOptions'                 => '000001',
                    'refreshTime'                     => '300',
                    'lang'                            => 'tr',
                    'merchantID'                      => '700655000200',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '406456',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'MAXTIPLIMIT'                     => '0.00',
                    'MaskedPan'                       => '435508***4358',
                    'Email'                           => 'mail@customer.com',
                    'Fadres'                          => '',
                    'merchantName'                    => 'İşbank 3d_pay Store',
                    'clientIp'                        => '89.244.149.137',
                    'girogateParamReqHash'            => '9Cfbi+RsV2HVXB5LNB68ypK5twIcyk7ZyOZ64rl7ZNs8c/QzMyFtReUmtIBLxrxzTEd2C04ImgQFjTWr/OsTOw==',
                    'okUrl'                           => 'http://localhost/akbank/3d-host/response.php',
                    'tismi'                           => '',
                    'md'                              => '435508:ABC94F203210DDBC157B3E04D9C1BF62BEC966DB554A878EFC62B4C7F75F045D:4183:##700655000200',
                    'taksit'                          => '',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'tcknvkn'                         => '',
                    'showdcchash'                     => '/M4AcoVyOILQme8b6dVSEFgTPo+AnXQRE2fGisdVUWUxV+oODWIYC3iOThJD1OqdDGC8M+wVQ/MN5Of7dWRS9Q==',
                    'storetype'                       => '3d_host',
                    'querycampainghash'               => 'dt+GSalwGNZfYZm/ZV5JbiTIo95NP8LP6Wvuihdc11sokCUczXbC6lUcCskKWEcrvIAtlAPs562Izc71fiYOVw==',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704861.1667120442.x-PxIquJzuV',
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABA##################UGZE=',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d-host/response.php',
                    'pbirimsembol'                    => 'TL ',
                    'xid'                             => 'J/CJIQ171+w/IOvf6CfmRXOYKfU=',
                    'checkisonushash'                 => 'jnjgIUP8ji/mifImB8JTrlA1Mc32r7DsD4cKeKD+RUEZG+POkS2hdsORLaUksXlpoc8DAuFvnOXcZZlRVMh35g==',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '202210305DCF',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'Fadres2'                         => '',
                    'Fismi'                           => '',
                    'clientid'                        => '700655000200',
                    'txstatus'                        => 'Y',
                    'tadres'                          => '',
                    'HASH'                            => 'EP5x+IDL3+TSIBXwTNG7YgKUoHY=',
                    'rnd'                             => 'wxI/n3+bu0CbyBo5OMXY',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '700655000200202210305DCF1ABABA##################UGZE=05435508:ABC94F203210DDBC157B3E04D9C1BF62BEC966DB554A878EFC62B4C7F75F045D:4183:##700655000200wxI/n3+bu0CbyBo5OMXY',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'transaction_security' => 'Full 3D Secure',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => '1',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'eci'                  => '05',
                    'cavv'                 => 'ABABA##################UGZE=',
                    'md_error_message'     => null,
                    'order_id'             => '202210305DCF',
                    'status'               => 'approved',
                    'payment_model'        => '3d_host',
                    'installment_count'    => 0,
                ],
            ],
            '3d_auth_fail1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'panFirst6'                       => '',
                    'TRANID'                          => '',
                    'tadres2'                         => '',
                    'SECMELIKAMPANYAKOD'              => '000002',
                    'PAResSyntaxOK'                   => 'true',
                    'querydcchash'                    => 'cDZR0kiNLFGnXovfVkcmh1/kTMHP4cOBai1GWNxy7Tdw0QYD6SruG2GmtwPJYiB4CACP941/mitzPYhRwIsJ9g==',
                    'panLast4'                        => '',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'campaignOptions'                 => '000002',
                    'refreshTime'                     => '300',
                    'lang'                            => 'tr',
                    'merchantID'                      => '700655000200',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '406456',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'MAXTIPLIMIT'                     => '0.00',
                    'MaskedPan'                       => '435508***4358',
                    'Email'                           => 'mail@customer.com',
                    'Fadres'                          => '',
                    'merchantName'                    => 'İşbank 3d_pay Store',
                    'clientIp'                        => '89.244.149.137',
                    'girogateParamReqHash'            => 'LDlrIEcHEBZjEO7LacpO0FbuhCEcmPjtVBxiaWV7DLMnorzP6fHeNl6aQKGD1PzkYBSHIyzQLl3pvD5n3AUxhA==',
                    'okUrl'                           => 'http://localhost/akbank/3d-host/response.php',
                    'tismi'                           => '',
                    'md'                              => '435508:EC7C0D35B47A5AB9CBF87E1062A6FC528B887325EAD2ED49C3E2ED3338E32006:4405:##700655000200',
                    'taksit'                          => '',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'tcknvkn'                         => '',
                    'showdcchash'                     => 'xGahhHd5b5Fpon+TtsAUbuifmuuvq/mNTM0e/5yjvyOF1bZHjnDEoc8HQVObxkgsgJlmfUoWVy/K3uEqPk+OTg==',
                    'storetype'                       => '3d_host',
                    'querycampainghash'               => 'ICWXZOhSTlmJ1Zl8CvlsInBd1/mObXeyaCAo9YVgEz1glY4638PIJQN6CrC6aR4rvgPtg9i4EQAMI5T7w/Cg/w==',
                    'mdErrorMsg'                      => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214705021.1667121056.gc2NvdPjGQ6',
                    'PAResVerified'                   => 'true',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d-host/response.php',
                    'pbirimsembol'                    => 'TL ',
                    'xid'                             => 'OCQM6dAL3/ahoUbE6JlWk3vlCsU=',
                    'checkisonushash'                 => '0pGKxQM71jDv3OEqKwcB/W7R1ZYXg6BSpGxeA5W6sc83OjX7vPeC36eCl0u4jH1CZtfICwSMflknF70O0S5ddQ==',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '20221030F11F',
                    'mdStatus'                        => '0',
                    'dsId'                            => '1',
                    'version'                         => '2.0',
                    'Fadres2'                         => '',
                    'Fismi'                           => '',
                    'clientid'                        => '700655000200',
                    'txstatus'                        => 'N',
                    'tadres'                          => '',
                    'HASH'                            => 'chQ2wvSGxQQRuzDEHcnBZkjD0fg=',
                    'rnd'                             => '5ZUbhzQFiV+w1VedcpUs',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '70065500020020221030F11F0435508:EC7C0D35B47A5AB9CBF87E1062A6FC528B887325EAD2ED49C3E2ED3338E32006:4405:##7006550002005ZUbhzQFiV+w1VedcpUs',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => null,
                    'transaction_type'     => 'pay',
                    'transaction_security' => 'MPI fallback',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => '0',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'eci'                  => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214705021.1667121056.gc2NvdPjGQ6',
                    'order_id'             => '20221030F11F',
                    'status'               => 'declined',
                    'payment_model'        => '3d_host',
                    'installment_count'    => 0,
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): array
    {
        return [
            'success1'               => [
                'responseData' => [
                    'ErrMsg'         => 'Record(s) found for 20221030FAC5',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '20221030FAC5',
                    'TransId'        => '22303Md4C19254',
                    'Extra'          => [
                        'AUTH_CODE'      => 'P90325',
                        'AUTH_DTTM'      => '2022-10-30 12:29:53.773',
                        'CAPTURE_AMT'    => '',
                        'CAPTURE_DTTM'   => '',
                        'CAVV_3D'        => '',
                        'CHARGE_TYPE_CD' => 'S',
                        'ECI_3D'         => '',
                        'HOSTDATE'       => '1030-122954',
                        'HOST_REF_NUM'   => '230300671782',
                        'MDSTATUS'       => '',
                        'NUMCODE'        => '0',
                        'ORDERSTATUS'    => "ORD_ID:20221030FAC5\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:\tTRANS_STAT:A\tAUTH_DTTM:2022-10-30 12:29:53.773\tCAPTURE_DTTM:\tAUTH_CODE:P90325\tTRANS_ID:22303Md4C19254",
                        'ORD_ID'         => '20221030FAC5',
                        'ORIG_TRANS_AMT' => '101',
                        'PAN'            => '4355 08** **** 4358',
                        'PROC_RET_CD'    => '00',
                        'SETTLEID'       => '',
                        'TRANS_ID'       => '22303Md4C19254',
                        'TRANS_STAT'     => 'A',
                        'XID_3D'         => '',
                    ],
                ],
                'expectedData' => [
                    'order_id'          => '20221030FAC5',
                    'auth_code'         => 'P90325',
                    'proc_return_code'  => '00',
                    'transaction_id'    => '22303Md4C19254',
                    'error_message'     => null,
                    'ref_ret_num'       => '230300671782',
                    'order_status'      => 'PAYMENT_COMPLETED',
                    'transaction_type'  => 'pay',
                    'masked_number'     => '4355 08** **** 4358',
                    'num_code'          => '0',
                    'first_amount'      => 1.01,
                    'capture_amount'    => null,
                    'currency'          => null,
                    'status'            => 'approved',
                    'error_code'        => null,
                    'status_detail'     => 'approved',
                    'capture'           => false,
                    'transaction_time'  => new \DateTimeImmutable('2022-10-30 12:29:53.773'),
                    'capture_time'      => null,
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'fail1'                  => [
                'responseData' => [
                    'ErrMsg'         => 'No record found for 2022103088D22',
                    'ProcReturnCode' => '99',
                    'Response'       => 'Declined',
                    'OrderId'        => '',
                    'TransId'        => '',
                    'Extra'          => [
                        'NUMCODE'     => '0',
                        'ORDERSTATUS' => "ORD_ID:\tCHARGE_TYPE_CD:\tORIG_TRANS_AMT:\tCAPTURE_AMT:\tTRANS_STAT:\tAUTH_DTTM:\tCAPTURE_DTTM:\tAUTH_CODE:",
                    ],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => '99',
                    'transaction_id'    => null,
                    'error_message'     => 'No record found for 2022103088D22',
                    'ref_ret_num'       => null,
                    'order_status'      => null,
                    'transaction_type'  => null,
                    'masked_number'     => null,
                    'first_amount'      => null,
                    'capture_amount'    => null,
                    'status'            => 'declined',
                    'error_code'        => null,
                    'status_detail'     => 'general_error',
                    'capture'           => null,
                    'transaction_time'  => null,
                    'capture_time'      => null,
                    'currency'          => null,
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'pay_order_status'       => [
                'responseData' => [
                    'ErrMsg'         => 'Record(s) found for 2024010354F1',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '2024010354F1',
                    'TransId'        => '24003Vl7F13152',
                    'Extra'          => [
                        'AUTH_CODE'      => 'P77974',
                        'AUTH_DTTM'      => '2024-01-03 21:37:57.259',
                        'CAPTURE_AMT'    => '101',
                        'CAPTURE_DTTM'   => '2024-01-03 21:37:57.259',
                        'CAVV_3D'        => '',
                        'CHARGE_TYPE_CD' => 'S',
                        'ECI_3D'         => '',
                        'HOSTDATE'       => '0103-213757',
                        'HOST_REF_NUM'   => '400300744233',
                        'MDSTATUS'       => '',
                        'NUMCODE'        => '0',
                        'ORDERSTATUS'    => "ORD_ID:2024010354F1\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:101\tTRANS_STAT:C\tAUTH_DTTM:2024-01-03 21:37:57.259\tCAPTURE_DTTM:2024-01-03 21:37:57.259\tAUTH_CODE:P77974\tTRANS_ID:24003Vl7F13152",
                        'ORD_ID'         => '2024010354F1',
                        'ORIG_TRANS_AMT' => '101',
                        'PAN'            => '4546 71** **** 7894',
                        'PROC_RET_CD'    => '00',
                        'SETTLEID'       => '',
                        'TRANS_ID'       => '24003Vl7F13152',
                        'TRANS_STAT'     => 'C',
                        'XID_3D'         => '',
                    ],
                ],
                'expectedData' => [
                    'auth_code'         => 'P77974',
                    'capture'           => true,
                    'capture_amount'    => 1.01,
                    'currency'          => null,
                    'error_code'        => null,
                    'error_message'     => null,
                    'first_amount'      => 1.01,
                    'masked_number'     => '4546 71** **** 7894',
                    'num_code'          => '0',
                    'order_id'          => '2024010354F1',
                    'order_status'      => 'PAYMENT_COMPLETED',
                    'proc_return_code'  => '00',
                    'ref_ret_num'       => '400300744233',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'transaction_time'  => new \DateTimeImmutable('2024-01-03 21:37:57.259'),
                    'capture_time'      => new \DateTimeImmutable('2024-01-03 21:37:57.259'),
                    'transaction_id'    => '24003Vl7F13152',
                    'transaction_type'  => 'pay',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'pre_pay_order_status'   => [
                'responseData' => [
                    'ErrMsg'         => 'Record(s) found for 202401032AF3',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '202401032AF3',
                    'TransId'        => '24003VqkA14152',
                    'Extra'          => [
                        'AUTH_CODE'      => 'T87380',
                        'AUTH_DTTM'      => '2024-01-03 21:42:35.902',
                        'CAPTURE_AMT'    => '',
                        'CAPTURE_DTTM'   => '',
                        'CAVV_3D'        => '',
                        'CHARGE_TYPE_CD' => 'S',
                        'ECI_3D'         => '',
                        'HOSTDATE'       => '0103-214236',
                        'HOST_REF_NUM'   => '400300744234',
                        'MDSTATUS'       => '',
                        'NUMCODE'        => '0',
                        'ORDERSTATUS'    => 'ORD_ID:202401032AF3	CHARGE_TYPE_CD:S	ORIG_TRANS_AMT:205	CAPTURE_AMT:	TRANS_STAT:A	AUTH_DTTM:2024-01-03 21:42:35.902	CAPTURE_DTTM:	AUTH_CODE:T87380	TRANS_ID:24003VqkA14152',
                        'ORD_ID'         => '202401032AF3',
                        'ORIG_TRANS_AMT' => '205',
                        'PAN'            => '4546 71** **** 7894',
                        'PROC_RET_CD'    => '00',
                        'SETTLEID'       => '',
                        'TRANS_ID'       => '24003VqkA14152',
                        'TRANS_STAT'     => 'A',
                        'XID_3D'         => '',
                    ],
                ],
                'expectedData' => [
                    'auth_code'         => 'T87380',
                    'capture'           => false,
                    'capture_amount'    => null,
                    'currency'          => null,
                    'error_code'        => null,
                    'error_message'     => null,
                    'first_amount'      => 2.05,
                    'masked_number'     => '4546 71** **** 7894',
                    'num_code'          => '0',
                    'order_id'          => '202401032AF3',
                    'order_status'      => 'PAYMENT_COMPLETED',
                    'proc_return_code'  => '00',
                    'ref_ret_num'       => '400300744234',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'transaction_time'  => new \DateTimeImmutable('2024-01-03 21:42:35.902'),
                    'capture_time'      => null,
                    'transaction_id'    => '24003VqkA14152',
                    'transaction_type'  => 'pay',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'canceled_order_status'  => [
                'responseData' => [
                    'ErrMsg'         => 'Record(s) found for 20240103BBF9',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '20240103BBF9',
                    'TransId'        => '24003VxrB15662',
                    'Extra'          => [
                        'AUTH_CODE'      => 'P42795',
                        'AUTH_DTTM'      => '2024-01-03 21:49:42.929',
                        'CAPTURE_AMT'    => '101',
                        'CAPTURE_DTTM'   => '2024-01-03 21:49:42.929',
                        'CAVV_3D'        => '',
                        'CHARGE_TYPE_CD' => 'S',
                        'ECI_3D'         => '',
                        'HOSTDATE'       => '0103-214944',
                        'HOST_REF_NUM'   => '400300744237',
                        'MDSTATUS'       => '',
                        'NUMCODE'        => '0',
                        'ORDERSTATUS'    => "ORD_ID:20240103BBF9\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:101\tTRANS_STAT:V\tAUTH_DTTM:2024-01-03 21:49:42.929\tCAPTURE_DTTM:2024-01-03 21:49:42.929\tAUTH_CODE:P42795\tTRANS_ID:24003VxrB15662",
                        'ORD_ID'         => '20240103BBF9',
                        'ORIG_TRANS_AMT' => '101',
                        'PAN'            => '4546 71** **** 7894',
                        'PROC_RET_CD'    => '00',
                        'SETTLEID'       => '',
                        'TRANS_ID'       => '24003VxrB15662',
                        'TRANS_STAT'     => 'V',
                        'VOID_DTTM'      => '2024-01-03 21:49:44.301',
                        'XID_3D'         => '',
                    ],
                ],
                'expectedData' => [
                    'auth_code'         => 'P42795',
                    'capture'           => true,
                    'capture_amount'    => 1.01,
                    'currency'          => null,
                    'error_code'        => null,
                    'error_message'     => null,
                    'first_amount'      => 1.01,
                    'masked_number'     => '4546 71** **** 7894',
                    'num_code'          => '0',
                    'order_id'          => '20240103BBF9',
                    'order_status'      => 'CANCELED',
                    'proc_return_code'  => '00',
                    'ref_ret_num'       => '400300744237',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'transaction_time'  => new \DateTimeImmutable('2024-01-03 21:49:42.929'),
                    'capture_time'      => new \DateTimeImmutable('2024-01-03 21:49:42.929'),
                    'transaction_id'    => '24003VxrB15662',
                    'transaction_type'  => 'pay',
                    'cancel_time'       => new \DateTimeImmutable('2024-01-03 21:49:44.301'),
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'refund_order_status'    => [
                'responseData' => [
                    'ErrMsg'         => 'Record(s) found for 20240128C0B7',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '20240128C0B7',
                    'TransId'        => '24028T8xG11980',
                    'Extra'          => [
                        'AUTH_CODE'      => 'P93736',
                        'AUTH_DTTM'      => '2024-01-28 19:58:49.382',
                        'CAPTURE_AMT'    => '201',
                        'CAPTURE_DTTM'   => '2024-01-28 19:58:49.382',
                        'CAVV_3D'        => '',
                        'CHARGE_TYPE_CD' => 'C',
                        'ECI_3D'         => '',
                        'HOSTDATE'       => '0128-195850',
                        'HOST_REF_NUM'   => '402800747548',
                        'MDSTATUS'       => '',
                        'NUMCODE'        => '0',
                        'ORDERSTATUS'    => 'ORD_ID:20240128C0B7	CHARGE_TYPE_CD:C	ORIG_TRANS_AMT:201	CAPTURE_AMT:201	TRANS_STAT:C	AUTH_DTTM:2024-01-28 19:58:49.382	CAPTURE_DTTM:2024-01-28 19:58:49.382	AUTH_CODE:P93736	TRANS_ID:24028T8xG11980',
                        'ORD_ID'         => '20240128C0B7',
                        'ORIG_TRANS_AMT' => '201',
                        'PAN'            => '4546 71** **** 7894',
                        'PROC_RET_CD'    => '00',
                        'SETTLEID'       => '',
                        'TRANS_ID'       => '24028T8xG11980',
                        'TRANS_STAT'     => 'C',
                        'XID_3D'         => '',
                    ],
                ],
                'expectedData' => [
                    'auth_code'         => 'P93736',
                    'capture'           => true,
                    'currency'          => null,
                    'error_code'        => null,
                    'error_message'     => null,
                    'first_amount'      => 2.01,
                    'capture_amount'    => 2.01,
                    'masked_number'     => '4546 71** **** 7894',
                    'num_code'          => '0',
                    'order_id'          => '20240128C0B7',
                    'order_status'      => 'PAYMENT_COMPLETED',
                    'proc_return_code'  => '00',
                    'ref_ret_num'       => '402800747548',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'transaction_time'  => new \DateTimeImmutable('2024-01-28 19:58:49.382'),
                    'capture_time'      => new \DateTimeImmutable('2024-01-28 19:58:49.382'),
                    'transaction_id'    => '24028T8xG11980',
                    'transaction_type'  => 'refund',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
            'recurring_order_status' => [
                'responseData' => [
                    'ErrMsg' => 'Record(s) found for 22303O8EA19252',
                    'Extra'  => [
                        'AUTH_CODE_1'          => 'P34325',
                        'AUTH_DTTM_1'          => '2022-10-30 14:58:03.449',
                        'CAPTURE_AMT_1'        => '101',
                        'CAPTURE_DTTM_1'       => '2022-10-30 14:58:03.449',
                        'CAVV_3D_1'            => '',
                        'CHARGE_TYPE_CD_1'     => 'S',
                        'CHARGE_TYPE_CD_2'     => 'S',
                        'ECI_3D_1'             => '',
                        'HOSTDATE_1'           => '1030-145804',
                        'HOST_REF_NUM_1'       => '230300671790',
                        'MDSTATUS_1'           => '',
                        'NUMCODE'              => '0',
                        'ORDERSTATUS_1'        => "ORD_ID:2022103097CD\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:101\tTRANS_STAT:C\tAUTH_DTTM:2022-10-30 14:58:03.449\tCAPTURE_DTTM:2022-10-30 14:58:03.449\tAUTH_CODE:P34325\tTRANS_ID:22303O8EB19253",
                        'ORDERSTATUS_2'        => "ORD_ID:2022103097CD-2\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tTRANS_STAT:PN\tPLANNED_START_DTTM:2023-01-30 14:58:03.449",
                        'ORD_ID_1'             => '2022103097CD',
                        'ORD_ID_2'             => '2022103097CD-2',
                        'ORIG_TRANS_AMT_1'     => '101',
                        'ORIG_TRANS_AMT_2'     => '101',
                        'PAN_1'                => '4355 08** **** 4358',
                        'PAN_2'                => '4355 08** **** 4358',
                        'PLANNED_START_DTTM_2' => '2023-01-30 14:58:03.449',
                        'PROC_RET_CD_1'        => '00',
                        'RECURRINGCOUNT'       => '2',
                        'RECURRINGID'          => '22303O8EA19252',
                        'SETTLEID_1'           => '',
                        'TRANS_ID_1'           => '22303O8EB19253',
                        'TRANS_STAT_1'         => 'C',
                        'TRANS_STAT_2'         => 'PN',
                        'XID_3D_1'             => '',
                    ],
                ],
                'expectedData' => [
                    'recurringId'               => '22303O8EA19252',
                    'recurringInstallmentCount' => '2',
                    'status'                    => 'approved',
                    'num_code'                  => '0',
                    'error_message'             => null,
                    'recurringOrders'           => [
                        [
                            'auth_code'        => 'P34325',
                            'capture'          => true,
                            'capture_amount'   => 1.01,
                            'currency'         => null,
                            'error_code'       => null,
                            'error_message'    => null,
                            'first_amount'     => 1.01,
                            'masked_number'    => '4355 08** **** 4358',
                            'order_id'         => '2022103097CD',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'proc_return_code' => '00',
                            'ref_ret_num'      => '230300671790',
                            'status'           => 'approved',
                            'status_detail'    => 'approved',
                            'transaction_time' => new \DateTimeImmutable('2022-10-30 14:58:03.449'),
                            'capture_time'     => new \DateTimeImmutable('2022-10-30 14:58:03.449'),
                            'transaction_id'   => '22303O8EB19253',
                            'transaction_type' => 'pay',
                        ],
                        [
                            'auth_code'        => null,
                            'capture'          => false,
                            'capture_amount'   => null,
                            'currency'         => null,
                            'error_code'       => null,
                            'error_message'    => null,
                            'first_amount'     => 1.01,
                            'masked_number'    => '4355 08** **** 4358',
                            'order_id'         => '2022103097CD-2',
                            'order_status'     => 'PAYMENT_PENDING',
                            'proc_return_code' => null,
                            'ref_ret_num'      => null,
                            'status'           => 'declined',
                            'status_detail'    => null,
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'transaction_type' => 'pay',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function cancelTestDataProvider(): array
    {
        return
            [
                'success1'                         => [
                    'responseData' => [
                        'OrderId'        => '20221030B3FF',
                        'GroupId'        => '20221030B3FF',
                        'Response'       => 'Approved',
                        'AuthCode'       => 'P43467',
                        'HostRefNum'     => '230300671786',
                        'ProcReturnCode' => '00',
                        'TransId'        => '22303MzZG10851',
                        'ErrMsg'         => '',
                        'Extra'          => [
                            'SETTLEID'   => '2287',
                            'TRXDATE'    => '20221030 12:51:25',
                            'ERRORCODE'  => '',
                            'TERMINALID' => '00655020',
                            'MERCHANTID' => '655000200',
                            'CARDBRAND'  => 'VISA',
                            'CARDISSUER' => 'AKBANK T.A.S.',
                            'HOSTDATE'   => '1030-125130',
                            'NUMCODE'    => '00',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20221030B3FF',
                        'group_id'         => '20221030B3FF',
                        'auth_code'        => 'P43467',
                        'ref_ret_num'      => '230300671786',
                        'proc_return_code' => '00',
                        'transaction_id'   => '22303MzZG10851',
                        'error_code'       => null,
                        'num_code'         => '00',
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'success_without_extra_error_code' => [
                    'responseData' => [
                        'OrderId'        => '230',
                        'GroupId'        => '800',
                        'Response'       => 'Approved',
                        'AuthCode'       => '160769',
                        'HostRefNum'     => '48',
                        'ProcReturnCode' => '00',
                        'TransId'        => '2836',
                        'ErrMsg'         => '',
                        'ERRORCODE'      => '',
                        'Extra'          => [
                            'KULLANILANPUAN'     => '000000000000',
                            'CARDBRAND'          => 'VISA',
                            'TRXDATE'            => '2017 13:14:06',
                            'KULLANILABILIRPUAN' => '000000000380',
                            'ACQSTAN'            => '769388',
                            'KAZANILANPUAN'      => '000000000229',
                            'TRACEID'            => '4d68eab86e6',
                            'NUMCODE'            => '00',
                            'SETTLEID'           => '87',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '230',
                        'group_id'         => '800',
                        'auth_code'        => '160769',
                        'ref_ret_num'      => '48',
                        'proc_return_code' => '00',
                        'transaction_id'   => '2836',
                        'error_code'       => null,
                        'num_code'         => '00',
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail_order_not_found_1'           => [
                    'responseData' => [
                        'OrderId'        => '',
                        'GroupId'        => '',
                        'Response'       => 'Error',
                        'AuthCode'       => '',
                        'HostRefNum'     => '',
                        'ProcReturnCode' => '99',
                        'TransId'        => '22303M5IA11121',
                        'ErrMsg'         => 'İptal edilmeye uygun satış işlemi bulunamadı.',
                        'Extra'          => [
                            'SETTLEID'  => '',
                            'TRXDATE'   => '20221030 12:55:08',
                            'ERRORCODE' => 'CORE-2008',
                            'NUMCODE'   => '992008',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'group_id'         => null,
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '99',
                        'transaction_id'   => '22303M5IA11121',
                        'error_code'       => 'CORE-2008',
                        'num_code'         => null,
                        'error_message'    => 'İptal edilmeye uygun satış işlemi bulunamadı.',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                    ],
                ],
                'fail_order_not_found_2'           => [
                    'responseData' => [
                        'OrderId'        => 'a1a7d184',
                        'GroupId'        => 'a1a7d184',
                        'Response'       => 'Declined',
                        'HostRefNum'     => '413719757716',
                        'ProcReturnCode' => '99',
                        'TransId'        => '',
                        'ErrMsg'         => 'İptal edilmeye uygun satış işlemi bulunamadı.',
                        'Extra'          => [
                            'TRXDATE'             => '20240516 22:56:09',
                            'EXTENDED_ERROR_CODE' => '215001',
                            'TRACEID'             => '3f423f86e9d886bf1cffae49d93268be',
                            'NUMCODE'             => '99',
                            'ERRORCODE'           => 'CORE-2008',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => 'a1a7d184',
                        'group_id'         => null,
                        'auth_code'        => null,
                        'ref_ret_num'      => '413719757716',
                        'proc_return_code' => '99',
                        'transaction_id'   => null,
                        'error_code'       => 'CORE-2008',
                        'num_code'         => null,
                        'error_message'    => 'İptal edilmeye uygun satış işlemi bulunamadı.',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                    ],
                ],
                'success_recurring_1'              => [
                    'responseData' => [
                        'RECURRINGOPERATION' => 'CANCEL',
                        'RECORDTYPE'         => 'ORDER',
                        'RECORDID'           => '2022103072C1-2',
                        'RESULT'             => 'Successfull',
                        'Extra'              => '',
                    ],
                    'expectedData' => [
                        'order_id' => '2022103072C1-2',
                        'status'   => 'approved',
                    ],
                ],
            ];
    }

    public static function refundTestDataProvider(): array
    {
        return [
            'success1' => [
                'responseData' => [
                    'OrderId'        => 'df0e',
                    'GroupId'        => 'dfc36f0e',
                    'Response'       => 'Approved',
                    'AuthCode'       => '46',
                    'HostRefNum'     => '41',
                    'ProcReturnCode' => '00',
                    'TransId'        => '24138',
                    'ErrMsg'         => '',
                    'ERRORCODE'      => '',
                    'Extra'          => [
                        'KULLANILANPUAN'     => '000000000000',
                        'CARDBRAND'          => 'MASTERCARD',
                        'CARDHOLDERNAME'     => 'ME* DE*',
                        'TRXDATE'            => '20240517 13:30:43',
                        'KULLANILABILIRPUAN' => '000000005450',
                        'ACQSTAN'            => '74',
                        'KAZANILANPUAN'      => '000000000000',
                        'TRACEID'            => 'e7ba2a6',
                        'NUMCODE'            => '00',
                        'SETTLEID'           => '',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => 'df0e',
                    'group_id'         => 'dfc36f0e',
                    'auth_code'        => '46',
                    'ref_ret_num'      => '41',
                    'proc_return_code' => '00',
                    'transaction_id'   => '24138',
                    'num_code'         => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'fail1'    => [
                'responseData' => [
                    'OrderId'        => '20221030B3FF',
                    'GroupId'        => '20221030B3FF',
                    'Response'       => 'Error',
                    'AuthCode'       => '',
                    'HostRefNum'     => '',
                    'ProcReturnCode' => '99',
                    'TransId'        => '22303M8rC11328',
                    'ErrMsg'         => 'Iade yapilamaz, siparis gunsonuna girmemis.',
                    'Extra'          => [
                        'SETTLEID'  => '',
                        'TRXDATE'   => '20221030 12:58:42',
                        'ERRORCODE' => 'CORE-2508',
                        'NUMCODE'   => '992508',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20221030B3FF',
                    'group_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => '99',
                    'transaction_id'   => '22303M8rC11328',
                    'num_code'         => null,
                    'error_code'       => 'CORE-2508',
                    'error_message'    => 'Iade yapilamaz, siparis gunsonuna girmemis.',
                    'status'           => 'declined',
                    'status_detail'    => 'general_error',
                ],
            ],
            'fail2'    => [
                'responseData' => [
                    'OrderId'    => '2c544d',
                    'Response'   => 'Declined',
                    'HostRefNum' => '413051',
                    'TransId'    => '24082',
                    'ErrMsg'     => 'Net Tutar 0.',
                    'Extra'      => [
                        'TRXDATE'   => '20240517 14:28:33',
                        'TRACEID'   => '73631448ab0c1e',
                        'ERRORCODE' => '215021',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '2c544d',
                    'group_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => '413051',
                    'proc_return_code' => null,
                    'transaction_id'   => '24082',
                    'num_code'         => null,
                    'error_code'       => '215021',
                    'error_message'    => 'Net Tutar 0.',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
        ];
    }

    public static function orderHistoryTestDataProvider(): array
    {
        return [
            'success_cancel_success_refund_fail' => [
                'responseData' => [
                    'ErrMsg'         => '',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '20240102D8F1',
                    'Extra'          => [
                        'TERMINALID' => '00655020',
                        'MERCHANTID' => '655000200',
                        'NUMCODE'    => '0',
                        'TRX1'       => "C\tD\t100\t100\t2024-01-02 21:53:02.486\t2024-01-02 21:53:02.486\t\t\t\t99\t24002V3CG19993",
                        'TRX2'       => "S\tV\t101\t101\t2024-01-02 21:52:59.261\t2024-01-02 21:52:59.261\t2024-01-02 21:53:01.297\t400200744059\tP78955\t00\t24002V29G19979",
                        'TRXCOUNT'   => '2',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20240102D8F1',
                    'proc_return_code' => '00',
                    'error_message'    => null,
                    'num_code'         => '0',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'        => 'P78955',
                            'proc_return_code' => '00',
                            'transaction_id'   => '24002V29G19979',
                            'error_message'    => null,
                            'ref_ret_num'      => '400200744059',
                            'order_status'     => 'CANCELED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-02 21:52:59.261'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => '99',
                            'transaction_id'   => '24002V3CG19993',
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'ERROR',
                            'transaction_type' => 'refund',
                            'first_amount'     => 1.0,
                            'capture_amount'   => 1.0,
                            'status'           => 'declined',
                            'error_code'       => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-02 21:53:02.486'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                            'status_detail'    => 'general_error',
                            'capture'          => false,
                            'currency'         => null,
                        ],
                    ],
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'success_payment_then_cancel_tx'     => [
                'responseData' => [
                    'ErrMsg'         => '',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '202401029F10',
                    'Extra'          => [
                        'TERMINALID' => '00655020',
                        'MERCHANTID' => '655000200',
                        'NUMCODE'    => '0',
                        'TRX1'       => "S\tV\t101\t101\t2024-01-02 21:47:28.785\t2024-01-02 21:47:28.785\t2024-01-02 21:47:40.156\t400200744054\tP77381\t00\t24002VvdA19109",
                        'TRXCOUNT'   => '1',
                    ],
                ],
                'expectedData' => [
                    'error_message'    => null,
                    'num_code'         => '0',
                    'order_id'         => '202401029F10',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'P77381',
                            'proc_return_code' => '00',
                            'transaction_id'   => '24002VvdA19109',
                            'error_message'    => null,
                            'ref_ret_num'      => '400200744054',
                            'order_status'     => 'CANCELED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-02 21:47:28.785'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'success_one_payment_tx'             => [
                'responseData' => [
                    'ErrMsg'         => '',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '202401010C20',
                    'Extra'          => [
                        'TERMINALID' => '00655020',
                        'MERCHANTID' => '655000200',
                        'NUMCODE'    => '0',
                        'TRX1'       => "S\tC\t101\t101\t2024-01-01 22:15:27.511\t2024-01-01 22:15:27.511\t\t400100743898\tP14578\t00\t24001WPbH16694",
                        'TRXCOUNT'   => '1',
                    ],
                ],
                'expectedData' => [
                    'error_message'    => null,
                    'num_code'         => '0',
                    'order_id'         => '202401010C20',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'P14578',
                            'proc_return_code' => '00',
                            'transaction_id'   => '24001WPbH16694',
                            'error_message'    => null,
                            'ref_ret_num'      => '400100743898',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-01 22:15:27.511'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'success_one_pre_auth_tx'            => [
                'responseData' => [
                    'ErrMsg'         => '',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '20240101CCCF',
                    'Extra'          => [
                        'TERMINALID' => '00655020',
                        'MERCHANTID' => '655000200',
                        'NUMCODE'    => '0',
                        'TRX1'       => "S\tA\t205\t\t2024-01-01 22:28:30.716\t\t\t400100743899\tT56045\t00\t24001WceJ18839",
                        'TRXCOUNT'   => '1',
                    ],
                ],
                'expectedData' => [
                    'error_message'    => null,
                    'num_code'         => '0',
                    'order_id'         => '20240101CCCF',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'T56045',
                            'proc_return_code' => '00',
                            'transaction_id'   => '24001WceJ18839',
                            'error_message'    => null,
                            'ref_ret_num'      => '400100743899',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 2.05,
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => false,
                            'currency'         => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-01 22:28:30.716'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'success_pre_auth_and_post_tx'       => [
                'responseData' => [
                    'ErrMsg'         => '',
                    'ProcReturnCode' => '00',
                    'Response'       => 'Approved',
                    'OrderId'        => '202401014456',
                    'Extra'          => [
                        'TERMINALID' => '00655020',
                        'MERCHANTID' => '655000200',
                        'NUMCODE'    => '0',
                        'TRX1'       => "S\tC\t200\t200\t2024-01-01 22:37:53.396\t2024-01-01 22:37:53.396\t\t400100743901\tT14446\t00\t24001Wl3G10348",
                        'TRXCOUNT'   => '1',
                    ],
                ],
                'expectedData' => [
                    'error_message'    => null,
                    'num_code'         => '0',
                    'order_id'         => '202401014456',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'T14446',
                            'proc_return_code' => '00',
                            'transaction_id'   => '24001Wl3G10348',
                            'error_message'    => null,
                            'ref_ret_num'      => '400100743901',
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 2.0,
                            'capture_amount'   => 2.0,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-01 22:37:53.396'),
                            'capture_time'     => null,
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'fail1'                              => [
                'responseData' => [
                    'ErrMsg'         => 'No record found for 20221030B3FF2',
                    'ProcReturnCode' => '05',
                    'Response'       => 'Declined',
                    'OrderId'        => '20221030B3FF2',
                    'Extra'          => [
                        'NUMCODE'  => '0',
                        'TRXCOUNT' => '0',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20221030B3FF2',
                    'proc_return_code' => '05',
                    'error_message'    => 'No record found for 20221030B3FF2',
                    'num_code'         => '0',
                    'trans_count'      => 0,
                    'transactions'     => [],
                    'status'           => 'declined',
                    'status_detail'    => 'reject',
                ],
            ],
        ];
    }
}
