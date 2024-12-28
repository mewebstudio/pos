<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapper;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class AkbankPosResponseDataMapperTest extends TestCase
{
    private AkbankPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new AkbankPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new AkbankPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger
        );
    }

    /**
     * @testWith ["VPS-0000", true]
     * ["VPS-1073", false]
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"responseCode": "VPS-0000"}, "VPS-0000"]
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
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $responseData,
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
        if ([] !== $responseData) {
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
     * @dataProvider refundDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($actualData);
        \ksort($expectedData);
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

        \ksort($actualData);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider orderHistoryDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapOrderHistoryResponse($responseData);
        if (isset($responseData['txnDetailList'])) {
            $this->assertCount($actualData['trans_count'], $actualData['transactions']);

            // we can't test this
            if (count($actualData['transactions']) > 1
                && null !== $actualData['transactions'][0]['transaction_time']
                && null !== $actualData['transactions'][1]['transaction_time']
            ) {
                $this->assertGreaterThan(
                    $actualData['transactions'][0]['transaction_time'],
                    $actualData['transactions'][1]['transaction_time'],
                );
            }

            foreach ($responseData['txnDetailList'] as $key => $tx) {
                if (isset($tx['txnDateTime'])) {
                    $this->assertEquals($expectedData['transactions'][$key]['transaction_time'], $actualData['transactions'][$key]['transaction_time']);
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

    public function testMapStatusResponse(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->responseDataMapper->mapStatusResponse([]);
    }

    /**
     * @dataProvider historyDataProvider
     */
    public function testMapHistoryResponse(array $response, int $expectedTxCount): void
    {
        $actual = $this->responseDataMapper->mapHistoryResponse($response);

        $this->assertCount($expectedTxCount, $actual['transactions']);
    }

    public static function paymentDataProvider(): iterable
    {
        yield 'success1' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'          => '1000',
                'responseCode'     => 'VPS-0000',
                'responseMessage'  => 'BAŞARILI',
                'hostResponseCode' => '00',
                'hostMessage'      => '000 ONAY KODU XXXXXX',
                'txnDateTime'      => '2022-03-01T09:29:23.851',
                'terminal'         => [
                    'merchantSafeId' => '********************************',
                    'terminalSafeId' => '********************************',
                ],
                'card'             => [
                    'cardHolderName' => '',
                ],
                'order'            => [
                    'orderId' => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                ],
                'transaction'      => [
                    'authCode'    => '064716',
                    'rrn'         => '206125059548',
                    'batchNumber' => 99,
                    'stan'        => 5,
                ],
                'campaign'         => [
                    'additionalInstallCount' => 0,
                    'deferingDate'           => '',
                    'deferingMonth'          => 0,
                ],
                'reward'           => [
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2022-03-01T09:29:23.851'),
                'auth_code'         => '064716',
                'order_id'          => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => '206125059548',
                'proc_return_code'  => 'VPS-0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => 2,
                'batch_num'         => 99,
            ],
        ];

        yield 'fail_wrong_datetime' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'         => '1000',
                'terminal'        => [
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                ],
                'responseMessage' => 'Geçerli talep tarihi giriniz.',
                'txnDateTime'     => '2024-04-14T19:26:05.928',
                'transaction'     => [
                    'stan'        => 0,
                    'rrn'         => '0',
                    'batchNumber' => 0,
                ],
                'responseCode'    => 'VPS-1073',
                'order'           => [
                    'orderId' => '20240414B80D',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '20240414B80D',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'VPS-1073',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1073',
                'error_message'     => 'Geçerli talep tarihi giriniz.',
                'installment_count' => 2,
                'batch_num'         => null,
            ],
        ];

        yield 'fail_duplicate_order' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'         => '1000',
                'terminal'        => [
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                ],
                'responseMessage' => 'Sipariş Numarası : 202404142607 tekil olmalıdır',
                'txnDateTime'     => '2024-04-14T16:16:55.366',
                'transaction'     => [
                    'stan'        => 0,
                    'rrn'         => '0',
                    'batchNumber' => 0,
                ],
                'responseCode'    => 'VPS-1013',
                'order'           => [
                    'orderId' => '202404142607',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '202404142607',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'VPS-1013',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1013',
                'error_message'     => 'Sipariş Numarası : 202404142607 tekil olmalıdır',
                'installment_count' => 2,
                'batch_num'         => null,
            ],
        ];
        yield 'fail_invalid_installment_count' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'reward'           => [
                    'pcbBalanceRewardAmount' => 0,
                    'pcbEarnedRewardAmount'  => 0,
                    'xcbEarnedRewardAmount'  => 0,
                    'ccbEarnedRewardAmount'  => 0,
                    'ccbBalanceRewardAmount' => 0,
                    'xcbBalanceRewardAmount' => 0,
                ],
                'txnCode'          => '1000',
                'hostResponseCode' => '83',
                'hostMessage'      => '183 TAKSIT TUTAR HAT',
                'campaign'         => [
                    'deferingMonth'          => 0,
                    'additionalInstallCount' => 0,
                ],
                'terminal'         => [
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                ],
                'responseMessage'  => 'Provizyon Hatası',
                'txnDateTime'      => '2024-04-23T16:14:00.264',
                'transaction'      => [
                    'authCode'    => '000000',
                    'stan'        => 5,
                    'rrn'         => '411524361163',
                    'batchNumber' => 50,
                ],
                'responseCode'     => 'VPS-1005',
                'order'            => [
                    'orderId' => '20240423303F',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '20240423303F',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => '411524361163',
                'proc_return_code'  => 'VPS-1005',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1005',
                'error_message'     => '183 TAKSIT TUTAR HAT',
                'installment_count' => 2,
                'batch_num'         => 50,
            ],
        ];

        yield 'success_post_pay' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'          => '1005',
                'responseCode'     => 'VPS-0000',
                'responseMessage'  => 'BAŞARILI',
                'hostResponseCode' => '00',
                'hostMessage'      => '000 ONAY KODU XXXXXX',
                'txnDateTime'      => '2022-03-01T11:52:17.914',
                'terminal'         => [
                    'merchantSafeId' => '********************************',
                    'terminalSafeId' => '********************************',
                ],
                'card'             => [
                    'cardHolderName' => '',
                ],
                'order'            => [
                    'orderId' => '0b06dc46-3243-4453-805f-d01cf51619fe',
                ],
                'transaction'      => [
                    'authCode'    => '064724',
                    'rrn'         => '206125059557',
                    'batchNumber' => 99,
                    'stan'        => 15,
                ],
                'campaign'         => [
                    'additionalInstallCount' => 0,
                    'deferingDate'           => '',
                    'deferingMonth'          => 0,
                ],
                'reward'           => [
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'post',
                'transaction_time'  => new \DateTimeImmutable('2022-03-01T11:52:17.914'),
                'auth_code'         => '064724',
                'order_id'          => '0b06dc46-3243-4453-805f-d01cf51619fe',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => '206125059557',
                'proc_return_code'  => 'VPS-0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => 2,
                'batch_num'         => 99,
            ],
        ];

        yield 'fail_post_pay_order_not_found' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'         => '1005',
                'terminal'        => [
                    'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                    'merchantSafeId' => '20230225213454627757B485BC1211C0',
                ],
                'responseMessage' => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404178828202404178828202404178828',
                'txnDateTime'     => '2024-04-17T13:53:32.511',
                'transaction'     => [
                    'stan'        => 0,
                    'rrn'         => '0',
                    'batchNumber' => 0,
                ],
                'responseCode'    => 'VPS-1007',
                'order'           => [
                    'orderId' => '202404178828202404178828202404178828',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'post',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '202404178828202404178828202404178828',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'VPS-1007',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1007',
                'error_message'     => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404178828202404178828202404178828',
                'installment_count' => 2,
                'batch_num'         => null,
            ],
        ];

        yield 'success_pre_pay' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 2,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'          => '1004',
                'responseCode'     => 'VPS-0000',
                'responseMessage'  => 'BAŞARILI',
                'hostResponseCode' => '00',
                'hostMessage'      => '000 ONAY KODU XXXXXX',
                'txnDateTime'      => '2022-03-01T11:36:02.020',
                'terminal'         => [
                    'merchantSafeId' => '********************************',
                    'terminalSafeId' => '********************************',
                ],
                'order'            => [
                    'orderId' => '0b06dc46-3243-4453-805f-d01cf51619fe',
                ],
                'transaction'      => [
                    'authCode'    => '064724',
                    'rrn'         => '206125059557',
                    'batchNumber' => 99,
                    'stan'        => 14,
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pre',
                'transaction_time'  => new \DateTimeImmutable('2022-03-01T11:52:17.914'),
                'auth_code'         => '064724',
                'order_id'          => '0b06dc46-3243-4453-805f-d01cf51619fe',
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => '206125059557',
                'proc_return_code'  => 'VPS-0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => 2,
                'batch_num'         => 99,
            ],
        ];
        yield 'success_recurring' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 0,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'          => '1000',
                'responseCode'     => 'VPS-0000',
                'responseMessage'  => 'BAŞARILI',
                'hostResponseCode' => '00',
                'hostMessage'      => '000 ONAY KODU XXXXXX',
                'txnDateTime'      => '2022-03-01T09:29:23.851',
                'terminal'         => [
                    'merchantSafeId' => '********************************',
                    'terminalSafeId' => '********************************',
                ],
                'card'             => [
                    'cardHolderName' => 'SP**** GO******',
                ],
                'order'            => [
                    'orderId'      => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                    'orderTrackId' => 'c3978ccf-6ef6-41e3-a987-8ca7e185c94e',
                ],
                'transaction'      => [
                    'authCode'    => '064716',
                    'rrn'         => '206125059548',
                    'batchNumber' => 99,
                    'stan'        => 5,
                ],
                'campaign'         => [
                    'additionalInstallCount' => 0,
                    'deferingDate'           => '',
                    'deferingMonth'          => 0,
                ],
                'reward'           => [
                    'ccbEarnedRewardAmount'  => 0,
                    'ccbBalanceRewardAmount' => 160282.58,
                    'ccbRewardDesc'          => 'CHIP-PARA 1',
                    'pcbEarnedRewardAmount'  => 110,
                    'pcbBalanceRewardAmount' => 102691.38,
                    'pcbRewardDesc'          => 'Ayhan PCB',
                    'xcbEarnedRewardAmount'  => 0.01,
                    'xcbBalanceRewardAmount' => 32610.53,
                    'xcbRewardDesc'          => 'BRISA DENEME',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable('2022-03-01T09:29:23.851'),
                'auth_code'         => '064716',
                'order_id'          => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                'recurring_id'      => 'c3978ccf-6ef6-41e3-a987-8ca7e185c94e',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => '206125059548',
                'proc_return_code'  => 'VPS-0000',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
                'batch_num'         => 99,
            ],
        ];

        yield 'fail_recurring' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 0,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'         => '1000',
                'recurring'       => [
                    'numberOfPayments'  => 2,
                    'frequencyInterval' => 3,
                    'frequencyCycle'    => 'M',
                ],
                'terminal'        => [
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                ],
                'responseMessage' => 'Bu üye işyerinin planlı ve tekrarlayan işlem yetkisi yoktur',
                'txnDateTime'     => '2024-04-14T16:00:34.899',
                'transaction'     => [
                    'stan'        => 0,
                    'rrn'         => '0',
                    'batchNumber' => 0,
                ],
                'responseCode'    => 'VPS-1061',
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => null,
                'recurring_id'      => null,
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'VPS-1061',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1061',
                'error_message'     => 'Bu üye işyerinin planlı ve tekrarlayan işlem yetkisi yoktur',
                'installment_count' => 0,
                'batch_num'         => null,
            ],
        ];
        yield 'fail_recurring_not_supported' => [
            'order'        => [
                'currency'    => PosInterface::CURRENCY_TRY,
                'amount'      => 1.01,
                'installment' => 0,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'txnCode'         => '1000',
                'recurring'       => [
                    'numberOfPayments'  => 2,
                    'frequencyInterval' => 3,
                    'frequencyCycle'    => 'M',
                ],
                'terminal'        => [
                    'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                    'merchantSafeId' => '20230225213454627757B485BC1211C0',
                ],
                'responseMessage' => 'Posnet işyerlerinde ECOM desteklenmemektedir.',
                'txnDateTime'     => '2024-04-16T14:47:17.456',
                'transaction'     => [
                    'stan'        => 0,
                    'rrn'         => '0',
                    'batchNumber' => 0,
                ],
                'responseCode'    => 'VPS-1060',
                'order'           => [
                    'orderId'      => '20240416E61B20240416E61B20240416E61B',
                    'orderTrackId' => '20240416E61B20240416E61B20240416E61B',
                ],
            ],
            'expectedData' => [
                'payment_model'     => 'regular',
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
                'auth_code'         => null,
                'order_id'          => '20240416E61B20240416E61B20240416E61B',
                'recurring_id'      => '20240416E61B20240416E61B20240416E61B',
                'currency'          => 'TRY',
                'amount'            => 1.01,
                'ref_ret_num'       => null,
                'proc_return_code'  => 'VPS-1060',
                'status'            => 'declined',
                'status_detail'     => null,
                'error_code'        => 'VPS-1060',
                'error_message'     => 'Posnet işyerlerinde ECOM desteklenmemektedir.',
                'installment_count' => null,
                'batch_num'         => null,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'success1'                               => [
                'order'              => [
                    'amount'      => 1.01,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'txnCode'         => '3001',
                    'responseCode'    => 'VPS-0000',
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-18T20:15:35.000',
                    'merchantSafeId'  => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'  => '2023090417500284633D137A249DBBEB',
                    'orderId'         => '20240418BA6C',
                    'secureEcomInd'   => '02',
                    'secureId'        => 'VG8yV2tCRHpTSlpNN2VqcDJRS1k=',
                    'secureData'      => 'kBM8+wZGAAAAAAAAAAAAAAAAAAAA',
                    'secureMd'        => '08A86B192287C69B2C443E7A42B29B5F46436C41DF8E159B4A232BB3D961940F',
                    'hashParams'      => 'txnCode+responseCode+responseMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+secureId+secureEcomInd+secureData+secureMd',
                    'hash'            => 'bFYReNscRIyo3EQQm18qB9iZEW5eqtx1UBAjwRAoVJuigugPKr4Rjcf4PgBHtrjg1IYFYAz8k3TCFcKWS0b4Xg==',
                ],
                'paymentData'        => [
                    'reward'           => [
                        'pcbBalanceRewardAmount' => 0,
                        'ccbRewardDesc'          => 'CHIP PARA',
                        'pcbEarnedRewardAmount'  => 0,
                        'xcbEarnedRewardAmount'  => 0,
                        'pcbRewardDesc'          => '',
                        'xcbRewardDesc'          => '',
                        'ccbEarnedRewardAmount'  => 0.01,
                        'ccbBalanceRewardAmount' => 215.61,
                        'xcbBalanceRewardAmount' => 0,
                    ],
                    'txnCode'          => '1000',
                    'hostResponseCode' => '00',
                    'hostMessage'      => '000 ONAY KODU XXXXXX',
                    'campaign'         => [
                        'deferingMonth'          => 2,
                        'additionalInstallCount' => 0,
                    ],
                    'terminal'         => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage'  => 'BAŞARILI',
                    'txnDateTime'      => '2024-04-18T20:15:38.036',
                    'card'             => [
                        'cardHolderName' => 'TD**',
                    ],
                    'transaction'      => [
                        'authCode'    => '306455',
                        'stan'        => 85,
                        'rrn'         => '411024360234',
                        'batchNumber' => 43,
                    ],
                    'responseCode'     => 'VPS-0000',
                    'order'            => [
                        'orderId' => '20240418BA6C',
                    ],
                ],
                'expectedData'       => [
                    'payment_model'        => '3d',
                    'md_status'            => null,
                    'masked_number'        => null,
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => '02',
                    'md_error_message'     => null,
                    'batch_num'            => 43,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2024-04-18T20:15:38.036'),
                    'transaction_security' => null,
                    'auth_code'            => '306455',
                    'ref_ret_num'          => '411024360234',
                    'proc_return_code'     => 'VPS-0000',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '20240418BA6C',
                    'recurring_id'         => null,
                    'installment_count'    => 0,
                ],
            ],
            '3d_auth_fail'                           => [
                'order'              => [
                    'amount'      => 1.01,
                    'installment' => 2,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    "txnCode"          => "",
                    "responseCode"     => "VPS-1279",
                    "responseMessage"  => "BKM 3DS Server Doğrulama Başarısız",
                    "hostResponseCode" => "",
                    "hostMessage"      => "",
                    "merchantSafeId"   => "",
                    "terminalSafeId"   => "",
                    "orderId"          => "20240418D4A620240418D4A620240418D4A6",
                    "hashParams"       => "responseCode+responseMessage+orderId",
                    "hash"             => "fFFtzKUArgzhM/nUbw6pM/EA7AjecFyMV13G+1/GVn9C0S1XNeWdtT8+x4swaxi5TZfqzwPy5wtDajxSfkZmRA==",
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'payment_model'        => '3d',
                    'md_status'            => null,
                    'masked_number'        => null,
                    'amount'               => null,
                    'currency'             => null,
                    'eci'                  => null,
                    'md_error_message'     => 'BKM 3DS Server Doğrulama Başarısız',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => 'VPS-1279',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '20240418D4A620240418D4A620240418D4A6',
                    'installment_count'    => null,
                ],
            ],
            '3d_auth_success_payment_fail'           => [
                'order'              => [
                    'amount'      => 1.01,
                    'installment' => 2,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'txnCode'         => '3001',
                    'responseCode'    => 'VPS-0000',
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-20T15:36:57.000',
                    'merchantSafeId'  => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'  => '2023090417500284633D137A249DBBEB',
                    'orderId'         => '20240420D268',
                    'secureEcomInd'   => '02',
                    'secureId'        => 'eGRBNFc3cWV1ZW5vVGtBZFB0WVQ=',
                    'secureData'      => 'kBMxdgkXAAAAAAAAAAAAAAAAAAAA',
                    'secureMd'        => '08A86B192287C69B2C443E7A42B29B5F46436C41DF8E159B4A232BB3D961940F',
                    'hashParams'      => 'txnCode+responseCode+responseMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+secureId+secureEcomInd+secureData+secureMd',
                    'hash'            => 'T3oigkrr9aWOkK/6yvv6izzTpTdBH+TMf6QpK184/9Ob4iYjs6PgCB4xcFt2w5UZuflzEWNye0558LzHk6CSmQ==',
                ],
                'paymentData'        => [
                    'reward'           => [
                        'pcbBalanceRewardAmount' => 0,
                        'pcbEarnedRewardAmount'  => 0,
                        'xcbEarnedRewardAmount'  => 0,
                        'ccbEarnedRewardAmount'  => 0,
                        'ccbBalanceRewardAmount' => 0,
                        'xcbBalanceRewardAmount' => 0,
                    ],
                    'txnCode'          => '1000',
                    'hostResponseCode' => '83',
                    'hostMessage'      => '183 TAKSIT TUTAR HAT',
                    'campaign'         => [
                        'deferingMonth'          => 0,
                        'additionalInstallCount' => 0,
                    ],
                    'terminal'         => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage'  => 'Provizyon Hatası',
                    'txnDateTime'      => '2024-04-20T15:37:02.163',
                    'transaction'      => [
                        'authCode'    => '000000',
                        'stan'        => 11,
                        'rrn'         => '411224360680',
                        'batchNumber' => 46,
                    ],
                    'responseCode'     => 'VPS-1005',
                    'order'            => [
                        'orderId' => '20240420D268',
                    ],
                ],
                'expectedData'       => [
                    'payment_model'        => '3d',
                    'md_status'            => null,
                    'masked_number'        => null,
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => '02',
                    'md_error_message'     => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'batch_num'            => 46,
                    'ref_ret_num'          => '411224360680',
                    'proc_return_code'     => 'VPS-1005',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => 'VPS-1005',
                    'error_message'        => '183 TAKSIT TUTAR HAT',
                    'order_id'             => '20240420D268',
                    'recurring_id'         => null,
                    'installment_count'    => 2,
                ],
            ],
            '3d_auth_success_recurring_payment_fail' => [
                'order'              => [
                    'amount'      => 1.01,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'txnCode'         => '3001',
                    'responseCode'    => 'VPS-0000',
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-18T21:16:02.000',
                    'merchantSafeId'  => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'  => '2023090417500284633D137A249DBBEB',
                    'orderId'         => '20240418AAAC1DA10749683080E0EB8624EF',
                    'secureEcomInd'   => '02',
                    'secureId'        => 'a1QzY0d0MGVxdHJTYTdSdWlHeDE=',
                    'secureData'      => 'kBNMcQdTAAAAAAAAAAAAAAAAAAAA',
                    'secureMd'        => '08A86B192287C69B2C443E7A42B29B5F46436C41DF8E159B4A232BB3D961940F',
                    'hashParams'      => 'txnCode+responseCode+responseMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+secureId+secureEcomInd+secureData+secureMd',
                    'hash'            => '4RWWfIt+6FzRhayYWtHXlDDUKFEw+/zOX72HPUajxQRmGE9xVPOmpbhEBlgNO5x6YDvDof94VgKyQC6AhBEbxQ==',
                ],
                'paymentData'        => [
                    'txnCode'         => '1000',
                    'recurring'       => [
                        'numberOfPayments'  => 2,
                        'frequencyInterval' => 3,
                        'frequencyCycle'    => 'M',
                    ],
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Bu üye işyerinin planlı ve tekrarlayan işlem yetkisi yoktur',
                    'txnDateTime'     => '2024-04-18T21:16:05.352',
                    'transaction'     => [
                        'stan'        => 0,
                        'rrn'         => '0',
                        'batchNumber' => 0,
                    ],
                    'responseCode'    => 'VPS-1061',
                    'order'           => [
                        'orderId' => '20240418AAAC1DA10749683080E0EB8624EF',
                    ],
                ],
                'expectedData'       => [
                    'payment_model'        => '3d',
                    'md_status'            => null,
                    'masked_number'        => null,
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => '02',
                    'md_error_message'     => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => 'VPS-1061',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => 'VPS-1061',
                    'error_message'        => 'Bu üye işyerinin planlı ve tekrarlayan işlem yetkisi yoktur',
                    'order_id'             => '20240418AAAC1DA10749683080E0EB8624EF',
                    'recurring_id'         => null,
                    // todo 'recurring_id'         => null,
                    'installment_count'    => 0,
                    'batch_num'            => null,
                ],
            ],
        ];
    }


    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'     => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'txnCode'                => '1000',
                    'responseCode'           => 'VPS-0000',
                    'responseMessage'        => 'BAŞARILI',
                    'hostResponseCode'       => '00',
                    'hostMessage'            => '000 ONAY KODU XXXXXX',
                    'txnDateTime'            => '2024-04-18T20:27:45.000',
                    'merchantSafeId'         => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'         => '2023090417500284633D137A249DBBEB',
                    'cardHolderName'         => 'TD**',
                    'orderId'                => '2024041811DA',
                    'authCode'               => '306456',
                    'rrn'                    => '411024360235',
                    'batchNumber'            => '43',
                    'stan'                   => '86',
                    'additionalInstallCount' => '0',
                    'deferingMonth'          => '2',
                    'ccbEarnedRewardAmount'  => '0.01',
                    'ccbBalanceRewardAmount' => '215.62',
                    'ccbRewardDesc'          => 'CHIP PARA',
                    'pcbEarnedRewardAmount'  => '0.00',
                    'pcbBalanceRewardAmount' => '0.00',
                    'pcbRewardDesc'          => '',
                    'xcbEarnedRewardAmount'  => '0.00',
                    'xcbBalanceRewardAmount' => '0.00',
                    'xcbRewardDesc'          => '',
                    'hashParams'             => 'txnCode+responseCode+responseMessage+hostResponseCode+hostMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+cardHolderName+authCode+rrn+batchNumber+stan+additionalInstallCount+deferingMonth+ccbEarnedRewardAmount+ccbBalanceRewardAmount+ccbRewardDesc+pcbEarnedRewardAmount+pcbBalanceRewardAmount+xcbEarnedRewardAmount+xcbBalanceRewardAmount',
                    'hash'                   => 'PO/pybfGrY7fesPoAq2U2B1bkpudx659yMyjTnnfP/Cw5MKR1t7mKvRnZdPBxu9nCC7qJFdr3mJSPTdMwYc3SA==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => new \DateTimeImmutable('2024-04-18T20:27:45.000'),
                    'transaction_type'     => 'pay',
                    'transaction_security' => null,
                    'masked_number'        => null,
                    'auth_code'            => '306456',
                    'ref_ret_num'          => '411024360235',
                    'batch_num'            => 43,
                    'error_code'           => null,
                    'error_message'        => null,
                    'eci'                  => null,
                    'md_status'            => null,
                    'md_error_message'     => null,
                    'order_id'             => '2024041811DA',
                    'proc_return_code'     => 'VPS-0000',
                    'status'               => 'approved',
                    'status_detail'        => null,
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => 0,
                ],
            ],
            'auth_fail'    => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'txnCode'          => '',
                    'responseCode'     => 'VPS-1279',
                    'responseMessage'  => 'BKM 3DS Server Doğrulama Başarısız',
                    'hostResponseCode' => '',
                    'hostMessage'      => '',
                    'merchantSafeId'   => '',
                    'terminalSafeId'   => '',
                    'orderId'          => '202404180331',
                    'hashParams'       => 'responseCode+responseMessage+orderId',
                    'hash'             => 'ZKXJ0jQkkO2skSh1QC+9kVOpA2OoTNysPzJdhL1okUK6sZ0MS3T3tyu858c/ZBrI9MsgFfZxq/dLMakn9hqLMQ==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => null,
                    'transaction_type'     => 'pay',
                    'transaction_security' => null,
                    'masked_number'        => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'eci'                  => null,
                    'md_status'            => null,
                    'md_error_message'     => 'BKM 3DS Server Doğrulama Başarısız',
                    'order_id'             => '202404180331',
                    'proc_return_code'     => 'VPS-1279',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => 0,
                ],
            ],
            'payment_fail' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'txnCode'                => '1000',
                    'responseCode'           => 'VPS-1005',
                    'responseMessage'        => 'Provizyon Hatası',
                    'hostResponseCode'       => '83',
                    'hostMessage'            => '183 TAKSIT TUTAR HAT',
                    'txnDateTime'            => '2024-04-20T15:20:45.000',
                    'merchantSafeId'         => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'         => '2023090417500284633D137A249DBBEB',
                    'orderId'                => '202404207281',
                    'authCode'               => '000000',
                    'rrn'                    => '411224360676',
                    'batchNumber'            => '46',
                    'stan'                   => '7',
                    'additionalInstallCount' => '0',
                    'deferingMonth'          => '0',
                    'ccbEarnedRewardAmount'  => '0.00',
                    'ccbBalanceRewardAmount' => '0.00',
                    'pcbEarnedRewardAmount'  => '0.00',
                    'pcbBalanceRewardAmount' => '0.00',
                    'xcbEarnedRewardAmount'  => '0.00',
                    'xcbBalanceRewardAmount' => '0.00',
                    'hashParams'             => 'txnCode+responseCode+responseMessage+hostResponseCode+hostMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+authCode+rrn+batchNumber+stan+additionalInstallCount+deferingMonth+ccbEarnedRewardAmount+ccbBalanceRewardAmount+pcbEarnedRewardAmount+pcbBalanceRewardAmount+xcbEarnedRewardAmount+xcbBalanceRewardAmount ◀',
                    'hash'                   => 'FMUYkEgTEZHIamlfQcg4zejebAWrG1FBJYxdVKwIGad6yFsyQb0AvxSGPCE5IjhpWvrlnJveqAOsJMmf+PS1sw==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => null,
                    'transaction_type'     => 'pay',
                    'transaction_security' => null,
                    'masked_number'        => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'eci'                  => null,
                    'md_status'            => null,
                    'md_error_message'     => 'Provizyon Hatası',
                    'order_id'             => '202404207281',
                    'proc_return_code'     => 'VPS-1005',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 1.01,
                    'installment_count'    => 0,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1'  => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'txnCode'                => '1000',
                    'responseCode'           => 'VPS-0000',
                    'responseMessage'        => 'BAŞARILI',
                    'hostResponseCode'       => '00',
                    'hostMessage'            => '000 ONAY KODU XXXXXX',
                    'txnDateTime'            => '2024-04-18T20:56:28.000',
                    'merchantSafeId'         => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'         => '2023090417500284633D137A249DBBEB',
                    'cardHolderName'         => 'TD**',
                    'orderId'                => '2024041898FD',
                    'authCode'               => '306460',
                    'rrn'                    => '411024360239',
                    'batchNumber'            => '43',
                    'stan'                   => '90',
                    'additionalInstallCount' => '0',
                    'deferingMonth'          => '2',
                    'ccbEarnedRewardAmount'  => '0.01',
                    'ccbBalanceRewardAmount' => '215.66',
                    'ccbRewardDesc'          => 'CHIP PARA',
                    'pcbEarnedRewardAmount'  => '0.00',
                    'pcbBalanceRewardAmount' => '0.00',
                    'pcbRewardDesc'          => '',
                    'xcbEarnedRewardAmount'  => '0.00',
                    'xcbBalanceRewardAmount' => '0.00',
                    'xcbRewardDesc'          => '',
                    'hashParams'             => 'txnCode+responseCode+responseMessage+hostResponseCode+hostMessage+txnDateTime+merchantSafeId+terminalSafeId+orderId+cardHolderName+authCode+rrn+batchNumber+stan+additionalInstallCount+deferingMonth+ccbEarnedRewardAmount+ccbBalanceRewardAmount+ccbRewardDesc+pcbEarnedRewardAmount+pcbBalanceRewardAmount+xcbEarnedRewardAmount+xcbBalanceRewardAmount',
                    'hash'                   => 'o8cVLBkljHc+1Icpoa35agcd7UPvD57gFY+bp7MQYznHnOr3f71vvsJwnbIQ2hGGVJj1nAdkZrh/lcVCdtMA+g==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2024-04-18T20:56:28.000'),
                    'transaction_security' => null,
                    'auth_code'            => '306460',
                    'ref_ret_num'          => '411024360239',
                    'batch_num'            => 43,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => null,
                    'order_id'             => '2024041898FD',
                    'proc_return_code'     => 'VPS-0000',
                    'status'               => 'approved',
                    'payment_model'        => '3d_host',
                    'currency'             => 'TRY',
                    'eci'                  => null,
                    'amount'               => 1.01,
                    'installment_count'    => 0,
                ],
            ],
            'auth_fail' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'txnCode'          => '',
                    'responseCode'     => 'VPS-1279',
                    'responseMessage'  => 'BKM 3DS Server Doğrulama Başarısız',
                    'hostResponseCode' => '',
                    'hostMessage'      => '',
                    'merchantSafeId'   => '',
                    'terminalSafeId'   => '',
                    'orderId'          => '20240418452F',
                    'hashParams'       => 'responseCode+responseMessage+orderId',
                    'hash'             => 'iW4wwqGV9h1ydKhpvhQ9Qeq2pOW+/HK0OB1AXoUlt/WlajbphFswh4Jy6BxTl4RAb2OICUnw+gy3UBPudHl5YA==',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => null,
                    'md_error_message'     => 'BKM 3DS Server Doğrulama Başarısız',
                    'masked_number'        => null,
                    'order_id'             => '20240418452F',
                    'proc_return_code'     => 'VPS-1279',
                    'status'               => 'declined',
                    'payment_model'        => '3d_host',
                    'currency'             => 'TRY',
                    'eci'                  => null,
                    'amount'               => 1.01,
                    'installment_count'    => 0,
                ],
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return [
            'success1'                => [
                'responseData' => [
                    'txnCode'          => '1003',
                    'responseCode'     => 'VPS-0000',
                    'responseMessage'  => 'BAŞARILI',
                    'hostResponseCode' => '00',
                    'hostMessage'      => '000 ONAY KODU XXXXXX',
                    'txnDateTime'      => '2022-03-01T10:29:32.350',
                    'terminal'         => [
                        'merchantSafeId' => '********************************',
                        'terminalSafeId' => '********************************',
                    ],
                    'order'            => [
                        'orderId' => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => 'b9ebfdc5-304f-49c2-8065-a2c7481a5d1f',
                    'recurring_id'     => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-0000',
                    'transaction_id'   => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'fail_order_not_found'    => [
                'responseData' => [
                    'txnCode'         => '1003',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404142607',
                    'txnDateTime'     => '2024-04-14T15:29:04.610',
                    'responseCode'    => 'VPS-1007',
                    'order'           => [
                        'orderId' => '202404142607',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202404142607',
                    'recurring_id'     => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1007',
                    'transaction_id'   => null,
                    'error_code'       => 'VPS-1007',
                    'error_message'    => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404142607',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
            'success_recurring'       => [
                'responseData' => [
                    'txnCode'          => '1003',
                    'hostResponseCode' => '00',
                    'hostMessage'      => '000 ONAY KODU XXXXXX',
                    'terminal'         => [
                        'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                        'merchantSafeId' => '20230225213454627757B485BC1211C0',
                    ],
                    'responseMessage'  => 'BAŞARILI',
                    'txnDateTime'      => '2024-04-17T14:02:21.216',
                    'responseCode'     => 'VPS-0000',
                    'order'            => [
                        'orderId'      => '202404170908202404170908202404170908',
                        'orderTrackId' => '202404170908202404170908202404170908',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202404170908202404170908202404170908',
                    'recurring_id'     => '202404170908202404170908202404170908',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-0000',
                    'transaction_id'   => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'recurring_fail_bad_data' => [
                'responseData' => [
                    'txnCode'         => '1003',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Eksik veya geçersiz tekrarlayan ödeme alanları',
                    'txnDateTime'     => '2024-04-14T18:44:20.269',
                    'responseCode'    => 'VPS-1055',
                    'order'           => [
                        'orderId' => '202404142607',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202404142607',
                    'recurring_id'     => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1055',
                    'transaction_id'   => null,
                    'error_code'       => 'VPS-1055',
                    'error_message'    => 'Eksik veya geçersiz tekrarlayan ödeme alanları',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],

            'recurring_fail_order_not_found' => [
                'responseData' => [
                    'txnCode'         => '1003',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'txnDateTime'     => '2024-04-14T18:49:03.104',
                    'responseCode'    => 'VPS-1059',
                    'order'           => [
                        'orderTrackId' => 'a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'recurring_id'     => 'a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1059',
                    'transaction_id'   => null,
                    'error_code'       => 'VPS-1059',
                    'error_message'    => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
        ];
    }

    public static function refundDataProvider(): array
    {
        return [
            'success1'                       => [
                'responseData' => [
                    'txnCode'          => '1002',
                    'hostResponseCode' => '00',
                    'hostMessage'      => '000 ONAY KODU XXXXXX',
                    'terminal'         => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage'  => 'BAŞARILI',
                    'txnDateTime'      => '2024-04-16T13:57:47.496',
                    'transaction'      => [
                        'authCode'    => '305965',
                        'stan'        => 17,
                        'rrn'         => '410824359690',
                        'batchNumber' => 41,
                    ],
                    'responseCode'     => 'VPS-0000',
                    'order'            => [
                        'orderId' => '20240415E30D',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20240415E30D',
                    'auth_code'        => '305965',
                    'ref_ret_num'      => '410824359690',
                    'proc_return_code' => 'VPS-0000',
                    'transaction_id'   => null,
                    'recurring_id'     => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'fail_order_not_found'           => [
                'responseData' => [
                    'txnCode'         => '1002',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404142607',
                    'txnDateTime'     => '2024-04-14T15:32:08.002',
                    'transaction'     => [
                        'stan'        => 0,
                        'rrn'         => '0',
                        'batchNumber' => 0,
                    ],
                    'responseCode'    => 'VPS-1007',
                    'order'           => [
                        'orderId' => '202404142607',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202404142607',
                    'recurring_id'     => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1007',
                    'transaction_id'   => null,
                    'error_code'       => 'VPS-1007',
                    'error_message'    => 'Orjinal İşlem bulunamadı. Sipariş Numarası : 202404142607',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
            'fail_already_canceled'          => [
                'responseData' => [
                    'txnCode'         => '1002',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Daha önce iptal edilmiş işlem',
                    'txnDateTime'     => '2024-04-15T20:46:56.886',
                    'transaction'     => [
                        'stan'        => 0,
                        'rrn'         => '0',
                        'batchNumber' => 0,
                    ],
                    'responseCode'    => 'VPS-1008',
                    'order'           => [
                        'orderId' => '202404153EB9',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202404153EB9',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1008',
                    'transaction_id'   => null,
                    'recurring_id'     => null,
                    'error_code'       => 'VPS-1008',
                    'error_message'    => 'Daha önce iptal edilmiş işlem',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
            'recurring'                      => [
                'responseData' => [
                    'txnCode'          => '1002',
                    'responseCode'     => 'VPS-0000',
                    'responseMessage'  => 'BAŞARILI',
                    'hostResponseCode' => '00',
                    'hostMessage'      => '000 ONAY KODU XXXXXX',
                    'txnDateTime'      => '2022-03-01T11:31:02.535',
                    'terminal'         => [
                        'merchantSafeId' => '********************************',
                        'terminalSafeId' => '********************************',
                    ],
                    'order'            => [
                        'orderId'      => 'e56673b8-d52b-47e4-bfa8-e9784b67e668',
                        'orderTrackId' => 'e56673b8-d52b-47e4-bfa8-e9784b67e668',
                    ],
                    'transaction'      => [
                        'authCode'    => '064723',
                        'rrn'         => '206125059556',
                        'batchNumber' => 99,
                        'stan'        => 13,
                    ],
                ],
                'expectedData' => [
                    'order_id'         => 'e56673b8-d52b-47e4-bfa8-e9784b67e668',
                    'recurring_id'     => 'e56673b8-d52b-47e4-bfa8-e9784b67e668',
                    'auth_code'        => '064723',
                    'ref_ret_num'      => '206125059556',
                    'proc_return_code' => 'VPS-0000',
                    'transaction_id'   => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
            'recurring_fail_order_not_found' => [
                'responseData' => [
                    'txnCode'         => '1002',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'txnDateTime'     => '2024-04-14T18:53:16.558',
                    'transaction'     => [
                        'stan'        => 0,
                        'rrn'         => '0',
                        'batchNumber' => 0,
                    ],
                    'responseCode'    => 'VPS-1059',
                    'order'           => [
                        'orderTrackId' => 'a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    ],
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'recurring_id'     => 'a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => 'VPS-1059',
                    'transaction_id'   => null,
                    'error_code'       => 'VPS-1059',
                    'error_message'    => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'status'           => 'declined',
                    'status_detail'    => null,
                ],
            ],
        ];
    }

    public static function orderHistoryDataProvider(): array
    {
        return [
            'success_only_payment_transaction'     => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 1.01,
                            'authCode'         => '305957',
                            'hostResponseCode' => '00',
                            'orderId'          => '20240415ABDD',
                            'pcbRewardAmount'  => 0,
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-15T20:41:03.165',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410724359540',
                            'installCount'     => 1,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1000',
                            'txnStatus'        => 'N',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orgOrderId'       => '20240415ABDD',
                            'stan'             => 137,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-15T20:41:09.363',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '20240415ABDD',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'         => '305957',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T20:41:03.165'),
                            'capture_time'      => new \DateTimeImmutable('2024-04-15T20:41:03.165'),
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359540',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_COMPLETED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => 1.01,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => true,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                            'batch_num'         => 39,
                        ],
                    ],
                ],
            ],
            'success_pay_then_cancel'              => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 1.01,
                            'authCode'         => '305947',
                            'hostResponseCode' => '00',
                            'orderId'          => '20240415A05F',
                            'pcbRewardAmount'  => 0,
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-15T19:02:55.454',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410724359526',
                            'installCount'     => 1,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1000',
                            'txnStatus'        => 'V',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orgOrderId'       => '20240415A05F',
                            'stan'             => 120,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-15T19:03:03.274',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '20240415A05F',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'         => '305947',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T19:02:55.454'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359526',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'CANCELED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                            'batch_num'         => 39,
                        ],
                    ],
                ],
            ],
            'success_pre_pay'                      => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 30,
                            'authCode'         => '305950',
                            'hostResponseCode' => '00',
                            'orderId'          => '2024041571A8',
                            'pcbRewardAmount'  => 0,
                            'preAuthStatus'    => 'O',
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-15T19:18:47.188',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410724359533',
                            'installCount'     => 3,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1004',
                            'txnStatus'        => 'N',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orgOrderId'       => '2024041571A8',
                            'stan'             => 127,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-15T19:18:56.848',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '2024041571A8',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'         => '305950',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T19:18:47.188'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359533',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PRE_AUTH_COMPLETED',
                            'transaction_type'  => 'pre',
                            'capture_amount'    => null,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 30.0,
                            'installment_count' => 3,
                            'batch_num'         => 39,
                        ],
                    ],
                ],
            ],
            'success_pre_pay_post_pay'             => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'preAuthCloseDate'   => '2024-04-15T20:17:20.158',
                            'amount'             => 30,
                            'authCode'           => '305955',
                            'hostResponseCode'   => '00',
                            'orderId'            => '20240415CE8F',
                            'pcbRewardAmount'    => 0,
                            'preAuthStatus'      => 'C',
                            'ccbRewardAmount'    => 0,
                            'settlementId'       => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber'   => '521807******2834',
                            'txnDateTime'        => '2024-04-15T20:17:19.205',
                            'responseCode'       => 'VPS-0000',
                            'rrn'                => '410724359538',
                            'installCount'       => 3,
                            'xcbRewardAmount'    => 0,
                            'txnCode'            => '1004',
                            'txnStatus'          => 'N',
                            'hostMessage'        => '000 ONAY KODU XXXXXX',
                            'orgOrderId'         => '20240415CE8F',
                            'stan'               => 134,
                            'preAuthCloseAmount' => 30,
                            'responseMessage'    => 'BAŞARILI',
                            'currencyCode'       => 949,
                            'batchNumber'        => 39,
                        ],
                        [
                            'amount'           => 30,
                            'authCode'         => '305955',
                            'hostResponseCode' => '00',
                            'orderId'          => 'd2f58999-5143-4f5c-b503-deaa789441d5',
                            'pcbRewardAmount'  => 0,
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-15T20:17:20.158',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410724359538',
                            'installCount'     => 3,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1005',
                            'txnStatus'        => 'N',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orgOrderId'       => '20240415CE8F',
                            'stan'             => 135,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-15T20:17:29.058',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '20240415CE8F',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'         => '305955',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T20:17:19.205'),
                            'capture_time'      => new \DateTimeImmutable('2024-04-15T20:17:20.158'),
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359538',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_COMPLETED',
                            'transaction_type'  => 'pre',
                            'capture_amount'    => 30.0,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => true,
                            'currency'          => 'TRY',
                            'first_amount'      => 30.0,
                            'installment_count' => 3,
                            'batch_num'         => 39,
                        ],
                        [
                            'auth_code'         => '305955',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T20:17:20.158'),
                            'capture_time'      => new \DateTimeImmutable('2024-04-15T20:17:20.158'),
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359538',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_COMPLETED',
                            'transaction_type'  => 'post',
                            'capture_amount'    => 30.0,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => true,
                            'currency'          => 'TRY',
                            'first_amount'      => 30.0,
                            'installment_count' => 3,
                            'batch_num'         => 39,
                        ],
                    ],
                ],
            ],
            'success_pre_pay_post_pay_then_cancel' => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 30,
                            'authCode'         => '305971',
                            'hostResponseCode' => '00',
                            'orderId'          => '5cb3fdd5-719e-4670-a2a7-4b400e5e1527',
                            'pcbRewardAmount'  => 0,
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-15T21:02:45.940',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410724359554',
                            'installCount'     => 3,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1005',
                            'txnStatus'        => 'V',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orgOrderId'       => '202404153DBC',
                            'stan'             => 156,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                        [
                            'preAuthCloseDate'   => '1900-01-01T00:00:00.000',
                            'amount'             => 30,
                            'authCode'           => '305971',
                            'hostResponseCode'   => '00',
                            'orderId'            => '202404153DBC',
                            'pcbRewardAmount'    => 0,
                            'preAuthStatus'      => 'O',
                            'ccbRewardAmount'    => 0,
                            'settlementId'       => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber'   => '521807******2834',
                            'txnDateTime'        => '2024-04-15T21:02:44.415',
                            'responseCode'       => 'VPS-0000',
                            'rrn'                => '410724359554',
                            'installCount'       => 3,
                            'xcbRewardAmount'    => 0,
                            'txnCode'            => '1004',
                            'txnStatus'          => 'N',
                            'hostMessage'        => '000 ONAY KODU XXXXXX',
                            'orgOrderId'         => '202404153DBC',
                            'stan'               => 155,
                            'preAuthCloseAmount' => 0,
                            'responseMessage'    => 'BAŞARILI',
                            'currencyCode'       => 949,
                            'batchNumber'        => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-15T21:02:55.875',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '202404153DBC',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'         => '305971',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T21:02:44.415'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359554',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PRE_AUTH_COMPLETED',
                            'transaction_type'  => 'pre',
                            'capture_amount'    => null,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 30.0,
                            'installment_count' => 3,
                            'batch_num'         => 39,
                        ],
                        [
                            'auth_code'         => '305971',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-15T21:02:45.940'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => '410724359554',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'CANCELED',
                            'transaction_type'  => 'post',
                            'capture_amount'    => null,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 30.0,
                            'installment_count' => 3,
                            'batch_num'         => 39,
                        ],
                    ],
                ],
            ],
            'success_failed_transaction'           => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 1.01,
                            'hostResponseCode' => '05',
                            'orderId'          => '202404142607',
                            'pcbRewardAmount'  => 0,
                            'ccbRewardAmount'  => 0,
                            'settlementId'     => 'ca0fff81-1379-4e8f-a86c-d505b4eb441c',
                            'maskedCardNumber' => '435509******5232',
                            'txnDateTime'      => '2024-04-14T15:24:43.133',
                            'responseCode'     => 'VPS-1005',
                            'rrn'              => '410624359235',
                            'installCount'     => 1,
                            'xcbRewardAmount'  => 0,
                            'txnCode'          => '1000',
                            'txnStatus'        => 'N',
                            'hostMessage'      => '005 RED-ONAYLANMADI',
                            'orgOrderId'       => '202404142607',
                            'stan'             => 19,
                            'responseMessage'  => 'Provizyon Hatası',
                            'currencyCode'     => 949,
                            'batchNumber'      => 39,
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-14T16:07:59.783',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '202404142607',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => 'VPS-1005',
                            'transaction_id'    => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-14T15:24:43.133'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => null,
                            'order_status'      => null,
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => 'declined',
                            'error_code'        => 'VPS-1005',
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                        ],
                    ],
                ],
            ],
            'fail_order_not_found'                 => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'İşlem bulunamadı. Sipariş Numarası : 2024041426072',
                    'txnDateTime'     => '2024-04-14T16:23:24.953',
                    'responseCode'    => 'VPS-1123',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'proc_return_code' => 'VPS-1123',
                    'error_code'       => 'VPS-1123',
                    'error_message'    => 'İşlem bulunamadı. Sipariş Numarası : 2024041426072',
                    'status'           => 'declined',
                    'status_detail'    => null,
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'success_recurring'                    => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                        'merchantSafeId' => '20230225213454627757B485BC1211C0',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 5,
                            'authCode'         => '306238',
                            'hostResponseCode' => '00',
                            'requestType'      => 'R',
                            'orderId'          => '202404176A19A9AFE57F9641B34F95C9A2F2',
                            'plannedDateTime'  => '2024-04-17T15:23:11.710',
                            'recurringOrder'   => 1,
                            'settlementId'     => '0ccf9416-5392-4a0b-b354-7f53718a7599',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-17T15:23:11.576',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '410924359975',
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orderTrackId'     => '202404176A19A9AFE57F9641B34F95C9A2F2',
                            'stan'             => 17,
                            'tryCount'         => 0,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'S',
                            'batchNumber'      => 21,
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 5,
                            'requestType'      => 'R',
                            'orderTrackId'     => '202404176A19A9AFE57F9641B34F95C9A2F2',
                            'plannedDateTime'  => '2024-07-17T15:23:11.846',
                            'recurringOrder'   => 2,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'W',
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 5,
                            'requestType'      => 'R',
                            'orderTrackId'     => '202404176A19A9AFE57F9641B34F95C9A2F2',
                            'plannedDateTime'  => '2024-10-17T15:23:11.846',
                            'recurringOrder'   => 3,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'W',
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-17T15:23:18.061',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '202404176A19A9AFE57F9641B34F95C9A2F2',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'         => '306238',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'recurring_order'   => 1,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-17T15:23:11.576'),
                            'capture_time'      => new \DateTimeImmutable('2024-04-17T15:23:11.576'),
                            'error_message'     => null,
                            'ref_ret_num'       => '410924359975',
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_COMPLETED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => 5.0,
                            'status'            => 'approved',
                            'status_detail'     => 'approved',
                            'error_code'        => null,
                            'capture'           => true,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                            'batch_num'         => 21,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 2,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_PENDING',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 3,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_PENDING',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                        ],
                    ],
                ],
            ],
            'success_recurring_first_canceled'     => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                        'merchantSafeId' => '20230225213454627757B485BC1211C0',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 5,
                            'authCode'         => '306288',
                            'hostResponseCode' => '00',
                            'requestType'      => 'R',
                            'orderId'          => '2024041701CDA2A3CBEA1DF6604F1BE3AC99',
                            'plannedDateTime'  => '2024-04-17T21:46:19.647',
                            'recurringOrder'   => 1,
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-17T21:46:19.618',
                            'responseCode'     => 'VPS-0000',
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orderTrackId'     => '2024041701CDA2A3CBEA1DF6604F1BE3AC99',
                            'tryCount'         => 0,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'V',
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 5,
                            'requestType'      => 'R',
                            'orderTrackId'     => '2024041701CDA2A3CBEA1DF6604F1BE3AC99',
                            'plannedDateTime'  => '2024-07-17T21:46:19.794',
                            'recurringOrder'   => 2,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'W',
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 5,
                            'requestType'      => 'R',
                            'orderTrackId'     => '2024041701CDA2A3CBEA1DF6604F1BE3AC99',
                            'plannedDateTime'  => '2024-10-17T21:46:19.794',
                            'recurringOrder'   => 3,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'W',
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-17T21:46:24.316',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '2024041701CDA2A3CBEA1DF6604F1BE3AC99',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'         => '306288',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'recurring_order'   => 1,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-17T21:46:19.618'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'CANCELED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => 'approved',
                            'status_detail'     => 'approved',
                            'error_code'        => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 2,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_PENDING',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 3,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_PENDING',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 5.0,
                            'installment_count' => 0,
                        ],
                    ],
                ],
            ],
            'success_recurring_all_canceled'       => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '20230225213454678B3D03B9C0057F40',
                        'merchantSafeId' => '20230225213454627757B485BC1211C0',
                    ],
                    'txnDetailList'   => [
                        [
                            'amount'           => 1.01,
                            'authCode'         => '306650',
                            'hostResponseCode' => '00',
                            'requestType'      => 'R',
                            'orderId'          => '20240420715E3319D6AF1A5B6C2DA58CC7E4',
                            'plannedDateTime'  => '2024-04-20T12:53:58.835',
                            'recurringOrder'   => 1,
                            'settlementId'     => '7a53f9d7-047b-45f0-819f-40241464ca49',
                            'maskedCardNumber' => '521807******2834',
                            'txnDateTime'      => '2024-04-20T12:53:58.793',
                            'responseCode'     => 'VPS-0000',
                            'rrn'              => '411224360666',
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'hostMessage'      => '000 ONAY KODU XXXXXX',
                            'orderTrackId'     => '20240420715E3319D6AF1A5B6C2DA58CC7E4',
                            'stan'             => 5,
                            'tryCount'         => 0,
                            'responseMessage'  => 'BAŞARILI',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'S',
                            'batchNumber'      => 22,
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 1.01,
                            'cancelDate'       => '2024-04-20T12:54:11.774',
                            'requestType'      => 'R',
                            'orderTrackId'     => '20240420715E3319D6AF1A5B6C2DA58CC7E4',
                            'plannedDateTime'  => '2024-07-20T12:53:58.988',
                            'recurringOrder'   => 2,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'C',
                        ],
                        [
                            'installCount'     => 1,
                            'txnCode'          => '1000',
                            'amount'           => 1.01,
                            'cancelDate'       => '2024-04-20T12:54:11.774',
                            'requestType'      => 'R',
                            'orderTrackId'     => '20240420715E3319D6AF1A5B6C2DA58CC7E4',
                            'plannedDateTime'  => '2024-10-20T12:53:58.988',
                            'recurringOrder'   => 3,
                            'tryCount'         => 0,
                            'maskedCardNumber' => '521807******2834',
                            'currencyCode'     => 949,
                            'requestStatus'    => 'C',
                        ],
                    ],
                    'responseMessage' => 'BAŞARILI',
                    'txnDateTime'     => '2024-04-20T12:59:57.362',
                    'responseCode'    => 'VPS-0000',
                ],
                'expectedData' => [
                    'order_id'         => '20240420715E3319D6AF1A5B6C2DA58CC7E4',
                    'proc_return_code' => 'VPS-0000',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'         => '306650',
                            'batch_num'         => 22,
                            'ref_ret_num'       => '411224360666',
                            'proc_return_code'  => 'VPS-0000',
                            'transaction_id'    => null,
                            'recurring_order'   => 1,
                            'transaction_time'  => new \DateTimeImmutable('2024-04-20T12:53:58.793'),
                            'capture_time'      => new \DateTimeImmutable('2024-04-20T12:53:58.793'),
                            'error_message'     => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'PAYMENT_COMPLETED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => 1.01,
                            'status'            => 'approved',
                            'status_detail'     => 'approved',
                            'error_code'        => null,
                            'capture'           => true,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 2,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'CANCELED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'recurring_order'   => 3,
                            'transaction_time'  => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'masked_number'     => '521807******2834',
                            'order_status'      => 'CANCELED',
                            'transaction_type'  => 'pay',
                            'capture_amount'    => null,
                            'status'            => null,
                            'error_code'        => null,
                            'status_detail'     => null,
                            'capture'           => null,
                            'currency'          => 'TRY',
                            'first_amount'      => 1.01,
                            'installment_count' => 0,
                        ],
                    ],
                ],
            ],
            'fail_recurring_not_found'             => [
                'responseData' => [
                    'txnCode'         => '1010',
                    'terminal'        => [
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    ],
                    'responseMessage' => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'txnDateTime'     => '2024-04-14T19:09:23.730',
                    'responseCode'    => 'VPS-1059',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'proc_return_code' => 'VPS-1059',
                    'error_code'       => 'VPS-1059',
                    'error_message'    => 'Planlı İşlem bulunamadı. Talimat Takip Numarası : a90d6b9b-3924-483e-b6a6-8a5dc2d62b24',
                    'status'           => 'declined',
                    'status_detail'    => null,
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
        ];
    }

    public static function historyDataProvider(): \Generator
    {
        $input = file_get_contents(__DIR__.'/../../test_data/akbankpos/history/daily_history.json');
        yield [
            'responseData'    => json_decode($input, true),
            'expectedTxCount' => 525,
        ];

        $input = file_get_contents(__DIR__.'/../../test_data/akbankpos/history/daily_history_2.json');

        yield [
            'responseData'    => json_decode($input, true),
            'expectedTxCount' => 8,
        ];

        $input = file_get_contents(__DIR__.'/../../test_data/akbankpos/history/daily_recurring_history.json');

        yield 'recurring' => [
            'responseData'    => json_decode($input, true),
            'expectedTxCount' => 7,
        ];

        yield 'failed' => [
            'responseData'    => [
                "requestId"       => "VPS00020599126001999999999920240425095529000904",
                "responseMessage" => "Gün aralığı 1 günden fazla girilemez",
                "responseCode"    => "VPS-2229",
            ],
            'expectedTxCount' => 0,
        ];

        yield 'no_transactions' => [
            'responseData'    => [
                'data'            => [
                    'txnDateTime'   => '2024-04-25T13:19:22.237',
                    'terminal'      => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'txnDetailList' => [

                    ],
                ],
                'requestId'       => 'VPS00020599128206999999999920240425131923000239',
                'terminal'        => [
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                ],
                'responseMessage' => 'SUCCESSFUL',
                'txnDateTime'     => '2024-04-25T13:19:22.237',
                'responseCode'    => 'VPS-0000',
            ],
            'expectedTxCount' => 0,
        ];


    }
}
