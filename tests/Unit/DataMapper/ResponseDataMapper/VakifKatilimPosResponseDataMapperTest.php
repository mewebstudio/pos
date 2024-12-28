<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class VakifKatilimPosResponseDataMapperTest extends TestCase
{
    private VakifKatilimPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new VakifKatilimPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );
        $this->responseDataMapper = new VakifKatilimPosResponseDataMapper(
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

        ksort($expectedData);
        ksort($actualData);
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

    public function testMap3DPayResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPayResponseData([], PosInterface::TX_TYPE_PAY_AUTH, []);
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
     * @dataProvider historyTestDataProvider
     */
    public function testMapHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);

        if (count($actualData['transactions']) > 1
            && null !== $actualData['transactions'][0]['transaction_time']
            && null !== $actualData['transactions'][1]['transaction_time']
        ) {
            $this->assertGreaterThan(
                $actualData['transactions'][0]['transaction_time'],
                $actualData['transactions'][1]['transaction_time'],
            );
        }

        $this->assertCount($actualData['trans_count'], $actualData['transactions']);

        foreach (array_keys($actualData['transactions']) as $key) {
            $this->assertEquals($expectedData['transactions'][$key]['transaction_time'], $actualData['transactions'][$key]['transaction_time'], 'tx: '.$key);
            $this->assertEquals($expectedData['transactions'][$key]['capture_time'], $actualData['transactions'][$key]['capture_time'], 'tx: '.$key);
            unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
            unset($actualData['transactions'][$key]['capture_time'], $expectedData['transactions'][$key]['capture_time']);
            \ksort($actualData['transactions'][$key]);
            \ksort($expectedData['transactions'][$key]);
        }

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    public function testMapHistoryResponseWithALotOfTxs(): void
    {
        $responseData = file_get_contents(__DIR__.'/../../test_data/vakifkatilimpos/history/success_history.json');

        $actualData = $this->responseDataMapper->mapHistoryResponse(json_decode($responseData, true));

        $this->assertCount(31, $actualData['transactions']);
        if (count($actualData['transactions']) <= 1) {
            return;
        }

        if (null === $actualData['transactions'][0]['transaction_time']) {
            return;
        }

        if (null === $actualData['transactions'][1]['transaction_time']) {
            return;
        }

        $this->assertGreaterThan(
            $actualData['transactions'][0]['transaction_time'],
            $actualData['transactions'][1]['transaction_time'],
        );
    }


    /**
     * @dataProvider orderHistoryTestDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapOrderHistoryResponse($responseData);

        if (count($actualData['transactions']) > 1
            && null !== $actualData['transactions'][0]['transaction_time']
            && null !== $actualData['transactions'][1]['transaction_time']
        ) {
            $this->assertGreaterThan(
                $actualData['transactions'][0]['transaction_time'],
                $actualData['transactions'][1]['transaction_time'],
            );
        }

        $this->assertCount($actualData['trans_count'], $actualData['transactions']);

        foreach (array_keys($actualData['transactions']) as $key) {
            $this->assertEquals($expectedData['transactions'][$key]['transaction_time'], $actualData['transactions'][$key]['transaction_time'], 'tx: '.$key);
            $this->assertEquals($expectedData['transactions'][$key]['capture_time'], $actualData['transactions'][$key]['capture_time'], 'tx: '.$key);
            unset($actualData['transactions'][$key]['cancel_time'], $expectedData['transactions'][$key]['cancel_time']);
            unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
            unset($actualData['transactions'][$key]['capture_time'], $expectedData['transactions'][$key]['capture_time']);
            \ksort($actualData['transactions'][$key]);
            \ksort($expectedData['transactions'][$key]);
        }

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    public static function paymentTestDataProvider(): iterable
    {
        yield 'fail_pre_auth' => [
            'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'responseData' => [
                'VPosMessage'     => [
                    'HashData'                         => 'fY5CK7nmMa9WkVdhV+u2Bp557n4=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '11111',
                    'UserName'                         => 'APIUSER',
                    'HashPassword'                     => 'kfkdsnskslkclswr9430ır',
                    'MerchantOrderId'                  => '58957265',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '840',
                    'DisplayAmount'                    => '840',
                    'FECAmount'                        => '0',
                    'FECCurrencyCode'                  => '0949',
                    'Products'                         => '',
                    'Addresses'                        => [
                        'VPosAddressContract' => [
                            'Type'        => '1',
                            'Name'        => 'Mahmut Sami YAZAR',
                            'PhoneNumber' => '324234234234',
                            'OrderId'     => '0',
                            'AddressId'   => '12',
                            'Email'       => 'mahmutsamiyazar@hotmail.com',
                        ],
                    ],
                    'APIVersion'                       => '1.0.0',
                    'CardNumber'                       => '5353550000958906',
                    'CardHolderName'                   => 'Hasan Karacan',
                    'PaymentType'                      => 'None',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'TransactionSecurity'              => '5',
                ],
                'IsEnrolled'      => 'true',
                'IsVirtual'       => 'false',
                'RRN'             => '922810016639',
                'Stan'            => '016639',
                'ResponseCode'    => '51',
                'ResponseMessage' => 'Limit Yetersiz.',
                'OrderId'         => '15188',
                'TransactionTime' => '2019-08-16T10:54:23.81069',
                'MerchantOrderId' => '58957265',
                'BusinessKey'     => '0',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'          => '58957265',
                'remote_order_id'   => '15188',
                'transaction_id'    => null,
                'transaction_type'  => 'pre',
                'transaction_time'  => null,
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => 'regular',
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'proc_return_code'  => '51',
                'status'            => 'declined',
                'status_detail'     => 'reject',
                'error_code'        => '51',
                'error_message'     => 'Limit Yetersiz.',
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
                'remote_order_id'   => '4480',
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
                'masked_number'     => '5124********1609',
                'installment_count' => 0,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'success1'      => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'ProvisionNumber' => '',
                    'MerchantOrderId' => '2024070152BF',
                    'OrderId'         => '6373034',
                    'RRN'             => '',
                    'Stan'            => '',
                    'HashData'        => 'tilHwVYboCx82++WZXg0I81LW6w=',
                    'MD'              => '/6auNEWM9TvyMZAuoM5Tjw==',
                ],
                'paymentData'        => [
                    'VPosMessage'     => [
                        'HashData'                         => '7tVy86ZXrcFCXLXL61Ayk0NkuBU=',
                        'MerchantId'                       => '1',
                        'SubMerchantId'                    => '0',
                        'CustomerId'                       => '222222',
                        'UserName'                         => 'apiuser',
                        'ReferenceNumber'                  => '7lQGu240701124943371',
                        'Rank'                             => '1',
                        'OkUrl'                            => 'https://localhost/pos/examples/vakif-katilim/3d/response.php',
                        'FailUrl'                          => 'https://localhost/pos/examples/vakif-katilim/3d/response.php',
                        'CommonPaymentPageAllowed'         => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'MerchantOrderIdUniqueControl'     => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'BatchId'                          => '0',
                        'ManuelBatch'                      => 'false',
                        'MerchantOrderId'                  => '2024070152BF',
                        'InstallmentCount'                 => '0',
                        'Amount'                           => '1001',
                        'FECAmount'                        => '0',
                        'TransactionSecurity'              => '3',
                        'AdditionalData'                   => [
                            'AdditionalDataList' => [
                                'VPosAdditionalData' => [
                                    'Key'  => 'MD',
                                    'Data' => '/6auNEWM9TvyMZAuoM5Tjw==',
                                ],
                            ],
                        ],
                        'Products'                         => '',
                        'Addresses'                        => '',
                        'APIVersion'                       => '1.0.0',
                        'InstallmentMaturityCommisionFlag' => '0',
                        'StartDate'                        => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'EndDate'                          => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'UpperLimit'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'LowerLimit'                       => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'RegisteredCardTransaction'        => '0',
                        'QueryId'                          => '0',
                        'DebtId'                           => '0',
                        'SurchargeAmount'                  => '0',
                        'SGKDebtAmount'                    => '0',
                        'VPSEntryMode'                     => 'None',
                        'OrderPOSTransactionId'            => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'TranDate'                         => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'EntryGateMethod'                  => 'VPOS_ThreeDModelPayGate',
                        'CardHolderCustomerId'             => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                        'PaymentId'                        => [
                            '@xsi:nil' => 'true',
                            '#'        => '',
                        ],
                    ],
                    'IsEnrolled'      => 'true',
                    'IsVirtual'       => 'false',
                    'ProvisionNumber' => '271425',
                    'RRN'             => '418312081069',
                    'Stan'            => '434824',
                    'ResponseCode'    => '00',
                    'ResponseMessage' => 'İşlem onaylandı',
                    'OrderId'         => '6373034',
                    'TransactionTime' => '2024-07-01T12:49:44.4281161',
                    'MerchantOrderId' => '2024070152BF',
                    'HashData'        => 'eNscG4h7B+Fx4/k0Dmt89HDP6nU=',
                    'BusinessKey'     => '0',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expectedData'       => [
                    'transaction_security' => null,
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'transaction_id'       => '434824',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2024-07-01T12:49:44.4281161'),
                    'auth_code'            => '271425',
                    'ref_ret_num'          => '418312081069',
                    'batch_num'            => '0',
                    'error_code'           => null,
                    'error_message'        => null,
                    'remote_order_id'      => '6373034',
                    'order_id'             => '2024070152BF',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 10.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'masked_number'        => null,
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
            '3d_auth_fail1' => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'ResponseCode'    => 'MPIAuthenticationStatusN',
                    'ResponseMessage' => '(N)Isleminiz gerceklestirelemedi. Kullanicinin 3d islem yapmasi engellendi.',
                    'ProvisionNumber' => '',
                    'MerchantOrderId' => '20240701F2F6',
                    'OrderId'         => '0',
                    'RRN'             => '',
                    'Stan'            => '',
                    'HashData'        => 'SVdI+hHXxg8GO0wY0hAcfRWpHyo=',
                    'MD'              => 'DpOHKpBUNVvU5Ld/FaeM6Q==',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_security' => null,
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => '(N)Isleminiz gerceklestirelemedi. Kullanicinin 3d islem yapmasi engellendi.',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => 'MPIAuthenticationStatusN',
                    'error_message'        => null,
                    'order_id'             => '20240701F2F6',
                    'proc_return_code'     => 'MPIAuthenticationStatusN',
                    'status'               => 'declined',
                    'status_detail'        => 'MPIAuthenticationStatusN',
                    'amount'               => null,
                    'currency'             => null,
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
        ];
    }

    public static function statusTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
                'VPosOrderData'   => null,
                'ResponseCode'    => 'MerchantNotDefined',
                'ResponseMessage' => 'Uye isyeri kullanici tanimi bulunamadi.',
                'MerchantOrderId' => '202403290D3D',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'auth_code'         => null,
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => null,
                'error_code'        => 'MerchantNotDefined',
                'error_message'     => 'Uye isyeri kullanici tanimi bulunamadi.',
                'first_amount'      => null,
                'installment_count' => null,
                'masked_number'     => null,
                'order_id'          => '202403290D3D',
                'order_status'      => null,
                'proc_return_code'  => 'MerchantNotDefined',
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

        yield 'fail_order_not_found' => [
            'responseData' => [
                'VPosOrderData'   => '',
                'ResponseCode'    => 'NonResult',
                'ResponseMessage' => 'Kriterlere uygun sonuc bulunmamaktadir.',
                'MerchantOrderId' => '124',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'auth_code'         => null,
                'capture'           => null,
                'capture_amount'    => null,
                'currency'          => null,
                'error_code'        => 'NonResult',
                'error_message'     => 'Kriterlere uygun sonuc bulunmamaktadir.',
                'first_amount'      => null,
                'installment_count' => null,
                'masked_number'     => null,
                'order_id'          => '124',
                'order_status'      => null,
                'proc_return_code'  => 'NonResult',
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

        yield 'success1' => [
            'responseData' => [
                'VPosOrderData'   => [
                    'OrderContract' => [
                        'OrderId'                        => '12743',
                        'MerchantOrderId'                => '1995434716',
                        'MerchantId'                     => '1',
                        'PosTerminalId'                  => '111111',
                        'OrderStatus'                    => '1',
                        'OrderStatusDescription'         => '',
                        'OrderType'                      => '1',
                        'OrderTypeDescription'           => '',
                        'TransactionStatus'              => '1',
                        'TransactionStatusDescription'   => 'Basarili',
                        'LastOrderStatus'                => '1',
                        'LastOrderStatusDescription'     => '',
                        'EndOfDayStatus'                 => '1',
                        'EndOfDayStatusDescription'      => 'Acik',
                        'FEC'                            => '0949',
                        'FecDescription'                 => 'TRY',
                        'TransactionSecurity'            => '1',
                        'TransactionSecurityDescription' => "3d'siz islem",
                        'CardHolderName'                 => 'Hasan Karacan',
                        'CardType'                       => 'MasterCard',
                        'CardNumber'                     => '5353********7017',
                        'OrderDate'                      => '2020-12-24T09:21:41.55',
                        'FirstAmount'                    => '9.30',
                        'FECAmount'                      => '0.00',
                        'CancelAmount'                   => '0.00',
                        'DrawbackAmount'                 => '0.00',
                        'ClosedAmount'                   => '0.00',
                        'InstallmentCount'               => '0',
                        'ResponseCode'                   => '00',
                        'ResponseExplain'                => 'Provizyon alındı.',
                        'ProvNumber'                     => '043290',
                        'RRN'                            => '035909014127',
                        'Stan'                           => '014127',
                        'MerchantUserName'               => 'USERNAME',
                        'BatchId'                        => '69',
                    ],
                ],
                'ResponseCode'    => '00',
                'ResponseMessage' => '',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'auth_code'         => '043290',
                'capture'           => false,
                'capture_amount'    => 0,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'first_amount'      => 9.3,
                'installment_count' => 0,
                'masked_number'     => '5353********7017',
                'order_id'          => '1995434716',
                'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                'payment_model'     => null,
                'proc_return_code'  => '00',
                'ref_ret_num'       => '035909014127',
                'refund_amount'     => null,
                'remote_order_id'   => '12743',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'transaction_id'    => '014127',
                'transaction_type'  => null,
                'transaction_time'  => new \DateTimeImmutable('2020-12-24T09:21:41.55'),
                'capture_time'      => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];
        yield 'success_canceled_order' => [
            'responseData' => [
                'VPosOrderData'   => [
                    'OrderContract' => [
                        'OrderId'                        => '6373591',
                        'MerchantOrderId'                => '20240701CF44',
                        'MerchantId'                     => '1',
                        'PosTerminalId'                  => '111111',
                        'OrderStatus'                    => '1',
                        'OrderStatusDescription'         => 'Satis',
                        'OrderType'                      => '1',
                        'OrderTypeDescription'           => 'Pesin',
                        'TransactionStatus'              => '1',
                        'TransactionStatusDescription'   => 'Basarili',
                        'LastOrderStatus'                => '6',
                        'LastOrderStatusDescription'     => 'Iptal',
                        'EndOfDayStatus'                 => '1',
                        'EndOfDayStatusDescription'      => 'Acik',
                        'FEC'                            => '0949',
                        'FecDescription'                 => 'TRY',
                        'TransactionSecurity'            => '3',
                        'TransactionSecurityDescription' => '3d islem',
                        'CardHolderName'                 => 'john doe',
                        'CardType'                       => 'MasterCard',
                        'CardNumber'                     => '5188********2666',
                        'OrderDate'                      => '2024-07-01T15:03:06.963',
                        'FirstAmount'                    => '10.01',
                        'TranAmount'                     => '0',
                        'FECAmount'                      => '0.00',
                        'CancelAmount'                   => '10.01',
                        'DrawbackAmount'                 => '0.00',
                        'ClosedAmount'                   => '0.00',
                        'InstallmentCount'               => '0',
                        'ResponseCode'                   => '00',
                        'ResponseExplain'                => 'İşlem onaylandı',
                        'ProvNumber'                     => '668468',
                        'RRN'                            => '418315149569',
                        'Stan'                           => '435384',
                        'MerchantUserName'               => 'apiuser',
                        'BatchId'                        => '1',
                    ],
                ],
                'ResponseCode'    => '00',
                'ResponseMessage' => '',
                'MerchantOrderId' => '20240701CF44',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'auth_code'         => '668468',
                'capture'           => false,
                'capture_amount'    => 0.0,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'first_amount'      => 10.01,
                'installment_count' => 0,
                'masked_number'     => '5188********2666',
                'order_id'          => '20240701CF44',
                'order_status'      => PosInterface::PAYMENT_STATUS_CANCELED,
                'payment_model'     => '3d',
                'proc_return_code'  => '00',
                'ref_ret_num'       => '418315149569',
                'refund_amount'     => null,
                'remote_order_id'   => '6373591',
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'transaction_id'    => '435384',
                'transaction_type'  => null,
                'transaction_time'  => new \DateTimeImmutable('2024-07-01T15:03:06.963'),
                'capture_time'      => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];
    }

    public static function cancelTestDataProvider(): iterable
    {
        yield 'success1' => [
            'responseData' => [
                'VPosMessage'     => [
                    'HashData'                         => 'EnbSVvhgUybfVzyB6yFMXyQVN2k=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '222222',
                    'UserName'                         => 'apiuser',
                    'ReferenceNumber'                  => 'WlgDy240701135444698',
                    'Rank'                             => '1',
                    'HashPassword'                     => 'DoxoW84N1hKFdV09SF4/FruhHm8=',
                    'CommonPaymentPageAllowed'         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'MerchantOrderIdUniqueControl'     => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'OrderId'                          => '6373447',
                    'BatchId'                          => '0',
                    'ManuelBatch'                      => 'false',
                    'MerchantOrderId'                  => '20240701BF8D',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '1001',
                    'FECAmount'                        => '0',
                    'TransactionSecurity'              => '0',
                    'Products'                         => '',
                    'Addresses'                        => '',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'StartDate'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EndDate'                          => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'UpperLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'LowerLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'RegisteredCardTransaction'        => '0',
                    'PaymentType'                      => '1',
                    'QueryId'                          => '0',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'VPSEntryMode'                     => 'None',
                    'OrderPOSTransactionId'            => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'TranDate'                         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EntryGateMethod'                  => 'VPOS_SaleReversal',
                    'CardHolderCustomerId'             => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'PaymentId'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                ],
                'IsEnrolled'      => 'true',
                'IsVirtual'       => 'false',
                'ProvisionNumber' => '593347',
                'RRN'             => '418313115082',
                'Stan'            => '435239',
                'ResponseCode'    => '00',
                'ResponseMessage' => 'İşlem onaylandı',
                'OrderId'         => '6373447',
                'TransactionTime' => '2024-07-01T13:54:45.1721751',
                'MerchantOrderId' => '20240701BF8D',
                'BusinessKey'     => '0',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'         => '20240701BF8D',
                'auth_code'        => null,
                'proc_return_code' => '00',
                'transaction_id'   => '435239',
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => '418313115082',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '6373447',
            ],
        ];

        yield 'fail_order_not_found' => [
            'responseData' => [
                'VPosMessage'     => [
                    'HashData'                         => 'w76+5POZzNsMGHVk93rvuhJW3JA=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '222222',
                    'UserName'                         => 'apiuser',
                    'ReferenceNumber'                  => 'MZGm2240701131546732',
                    'Rank'                             => '1',
                    'HashPassword'                     => 'DoxoW84N1hKFdV09SF4/FruhHm8=',
                    'CommonPaymentPageAllowed'         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'MerchantOrderIdUniqueControl'     => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'OrderId'                          => '1243',
                    'BatchId'                          => '0',
                    'ManuelBatch'                      => 'false',
                    'MerchantOrderId'                  => '124',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '1000',
                    'FECAmount'                        => '0',
                    'TransactionSecurity'              => '0',
                    'Products'                         => '',
                    'Addresses'                        => '',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'StartDate'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EndDate'                          => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'UpperLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'LowerLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'RegisteredCardTransaction'        => '0',
                    'PaymentType'                      => '1',
                    'QueryId'                          => '0',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'VPSEntryMode'                     => 'None',
                    'OrderPOSTransactionId'            => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'TranDate'                         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EntryGateMethod'                  => 'VPOS_SaleReversal',
                    'CardHolderCustomerId'             => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'PaymentId'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                ],
                'IsEnrolled'      => 'true',
                'IsVirtual'       => 'false',
                'ResponseCode'    => 'OrderDataNotFound',
                'ResponseMessage' => 'islem bilgisi bulunamadi.',
                'OrderId'         => '0',
                'TransactionTime' => '2024-07-01T13:15:47.2754872+03:00',
                'MerchantOrderId' => '124',
                'HashData'        => 'i8SYtpK1WT9uQ532aQwPxaEmaJE=',
                'BusinessKey'     => '0',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'         => '124',
                'auth_code'        => null,
                'proc_return_code' => 'OrderDataNotFound',
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => 'islem bilgisi bulunamadi.',
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => 'OrderDataNotFound',
                'status_detail'    => null,
                'remote_order_id'  => '0',
            ],
        ];
    }

    public static function refundTestDataProvider(): iterable
    {
        yield 'success1' => [
            'responseData' => [
                'VPosMessage'     => [
                    'HashData'                         => 'I7H/6nwfydM6VcwXsl82mqeC83o=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '11111',
                    'UserName'                         => 'APIUSER',
                    'CustomerIPAddress'                => '',
                    'OrderId'                          => '114293600',
                    'BatchId'                          => '',
                    'MerchantOrderId'                  => '2023070849CD',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '100',
                    'DisplayAmount'                    => '100',
                    'FECAmount'                        => '',
                    'FECCurrencyCode'                  => '0949',
                    'Products'                         => '',
                    'Addresses'                        => [
                        'VPosAddressContract' => [
                            'Type'        => '',
                            'Name'        => ' ',
                            'PhoneNumber' => '',
                            'OrderId'     => '',
                            'AddressId'   => '',
                            'Email'       => ' ',
                        ],
                    ],
                    'APIVersion'                       => '1.0.0',
                    'PaymentType'                      => '1',
                    'SurchargeAmount'                  => '',
                    'SGKDebtAmount'                    => '',
                    'InstallmentMaturityCommisionFlag' => '',
                    'TransactionSecurity'              => '',
                ],
                'IsEnrolled'      => 'false',
                'IsVirtual'       => 'false',
                'RRN'             => '904115005554',
                'Stan'            => '005554',
                'ResponseCode'    => '00',
                'ResponseMessage' => '',
                'OrderId'         => '114293600',
                'TransactionTime' => '2023-07-08T23:45:15.797',
                'MerchantOrderId' => '2023070849CD',
                'BusinessKey'     => '202208456498416947',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'         => '2023070849CD',
                'auth_code'        => null,
                'proc_return_code' => '00',
                'transaction_id'   => '005554',
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => '904115005554',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293600',
            ],
        ];

        yield 'fail_order_not_found' => [
            'responseData' => [
                'VPosMessage'     => [
                    'HashData'                         => 'cmW+Trusz5j7wCdExdDaFyPtzx0=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '222222',
                    'UserName'                         => 'apiuser',
                    'ReferenceNumber'                  => '74g81240701132558594',
                    'Rank'                             => '1',
                    'HashPassword'                     => 'DoxoW84N1hKFdV09SF4/FruhHm8=',
                    'CommonPaymentPageAllowed'         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'MerchantOrderIdUniqueControl'     => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'OrderId'                          => '1243',
                    'BatchId'                          => '0',
                    'ManuelBatch'                      => 'false',
                    'MerchantOrderId'                  => '124',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '0',
                    'FECAmount'                        => '0',
                    'TransactionSecurity'              => '0',
                    'Products'                         => '',
                    'Addresses'                        => '',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'StartDate'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EndDate'                          => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'UpperLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'LowerLimit'                       => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'RegisteredCardTransaction'        => '0',
                    'QueryId'                          => '0',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'VPSEntryMode'                     => 'None',
                    'OrderPOSTransactionId'            => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'TranDate'                         => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'EntryGateMethod'                  => 'VPOS_Drawback',
                    'CardHolderCustomerId'             => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                    'PaymentId'                        => [
                        '@xsi:nil' => 'true',
                        '#'        => '',
                    ],
                ],
                'IsEnrolled'      => 'true',
                'IsVirtual'       => 'false',
                'ResponseCode'    => 'OrderDataNotFound',
                'ResponseMessage' => 'islem bilgisi bulunamadi.',
                'OrderId'         => '0',
                'TransactionTime' => '2024-07-01T13:25:58.9066328+03:00',
                'MerchantOrderId' => '124',
                'BusinessKey'     => '0',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'         => '124',
                'auth_code'        => null,
                'proc_return_code' => 'OrderDataNotFound',
                'transaction_id'   => null,
                'currency'         => null,
                'error_message'    => 'islem bilgisi bulunamadi.',
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => 'OrderDataNotFound',
                'status_detail'    => null,
                'remote_order_id'  => '0',
            ],
        ];
    }

    public static function historyTestDataProvider(): array
    {
        return [
            'test1'                => [
                'input'    => [
                    'VPosOrderData'   => [
                        'OrderContract' => [
                            [
                                'OrderId'                        => '12754',
                                'MerchantOrderId'                => '709834990',
                                'MerchantId'                     => '1',
                                'PosTerminalId'                  => '111111',
                                'OrderStatus'                    => '1',
                                'OrderStatusDescription'         => 'Satis',
                                'OrderType'                      => '1',
                                'OrderTypeDescription'           => 'Pesin',
                                'TransactionStatus'              => '2',
                                'TransactionStatusDescription'   => 'Basarisiz',
                                'LastOrderStatus'                => '1',
                                'LastOrderStatusDescription'     => 'Satis',
                                'EndOfDayStatus'                 => '1',
                                'EndOfDayStatusDescription'      => 'Acik',
                                'FEC'                            => '0949',
                                'FecDescription'                 => 'TRY',
                                'TransactionSecurity'            => '5',
                                'TransactionSecurityDescription' => '',
                                'CardHolderName'                 => 'Hasan Karacan',
                                'CardType'                       => 'MasterCard',
                                'CardNumber'                     => '5353********3233',
                                'OrderDate'                      => '2020-12-25T12:13:35.74',
                                'TranAmount'                     => '3.90',
                                'FirstAmount'                    => '3.90',
                                'FECAmount'                      => '0.00',
                                'CancelAmount'                   => '0.00',
                                'DrawbackAmount'                 => '0.00',
                                'ClosedAmount'                   => '0.00',
                                'InstallmentCount'               => '0',
                                'ResponseCode'                   => '05',
                                'ResponseExplain'                => 'Hata Kodu5',
                                'ProvNumber'                     => '',
                                'RRN'                            => '03611114146',
                                'Stan'                           => '012246',
                                'MerchantUserName'               => 'USERNAME',
                                'BatchId'                        => '73',
                            ],
                            [
                                'OrderId'                        => '12753',
                                'MerchantOrderId'                => '424636131',
                                'MerchantId'                     => '1',
                                'PosTerminalId'                  => '111111',
                                'OrderStatus'                    => '1',
                                'OrderStatusDescription'         => 'Satis',
                                'OrderType'                      => '1',
                                'OrderTypeDescription'           => 'Pesin',
                                'TransactionStatus'              => '1',
                                'TransactionStatusDescription'   => 'Basarili',
                                'LastOrderStatus'                => '1',
                                'LastOrderStatusDescription'     => 'Satis',
                                'EndOfDayStatus'                 => '2',
                                'EndOfDayStatusDescription'      => 'Kapali',
                                'FEC'                            => '0949',
                                'FecDescription'                 => 'TRY',
                                'TransactionSecurity'            => '5',
                                'TransactionSecurityDescription' => '',
                                'CardHolderName'                 => 'Hasan Karacan',
                                'CardType'                       => 'MasterCard',
                                'CardNumber'                     => '5353********8906',
                                'OrderDate'                      => '2020-12-25T08:41:40.947',
                                'FirstAmount'                    => '2.70',
                                'FECAmount'                      => '0.00',
                                'CancelAmount'                   => '0.00',
                                'DrawbackAmount'                 => '0.00',
                                'ClosedAmount'                   => '0.00',
                                'InstallmentCount'               => '0',
                                'ResponseCode'                   => '00',
                                'ResponseExplain'                => 'Provizyon alındı.',
                                'ProvNumber'                     => '831168',
                                'RRN'                            => '036008014143',
                                'Stan'                           => '014143',
                                'MerchantUserName'               => 'USERNAME',
                                'BatchId'                        => '72',
                            ],
                        ],
                    ],
                    'ResponseCode'    => '00',
                    'ResponseMessage' => '',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expected' => [
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'         => '831168',
                            'proc_return_code'  => '00',
                            'transaction_id'    => '014143',
                            'transaction_time'  => new \DateTimeImmutable('2020-12-25T08:41:40.947'),
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => '036008014143',
                            'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                            'transaction_type'  => null,
                            'first_amount'      => 2.7,
                            'capture_amount'    => 0,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => false,
                            'currency'          => 'TRY',
                            'masked_number'     => '5353********8906',
                            'order_id'          => '424636131',
                            'remote_order_id'   => '12753',
                            'payment_model'     => 'regular',
                            'installment_count' => 0,
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => '05',
                            'transaction_id'   => '012246',
                            'transaction_time' => new \DateTimeImmutable('2020-12-25T12:13:35.74'),
                            'capture_time'     => null,
                            'error_message'    => 'Hata Kodu5',
                            'ref_ret_num'      => '03611114146',
                            'order_status'     => null,
                            'transaction_type' => null,
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => '05',
                            'status_detail'    => '05',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                            'order_id'         => '709834990',
                            'remote_order_id'  => '12754',
                            'payment_model'    => 'regular',
                        ],
                    ],
                ],
            ],
            'fail_order_not_found' => [
                'input'    => [
                    'VPosOrderData'   => '',
                    'ResponseCode'    => 'NonResult',
                    'ResponseMessage' => 'Kriterlere uygun sonuc bulunmamaktadir.',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expected' => [
                    'proc_return_code' => 'NonResult',
                    'error_code'       => 'NonResult',
                    'error_message'    => 'Kriterlere uygun sonuc bulunmamaktadir.',
                    'status'           => 'declined',
                    'status_detail'    => 'NonResult',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
        ];
    }

    public static function orderHistoryTestDataProvider(): array
    {
        return [
            'fail1'                   => [
                'input'    => [
                    'VPosOrderData'   => '',
                    'ResponseCode'    => 'MerchantNotDefined',
                    'ResponseMessage' => 'Uye isyeri kullanici tanimi bulunamadi.',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expected' => [
                    'proc_return_code' => 'MerchantNotDefined',
                    'order_id'         => null,
                    'remote_order_id'  => null,
                    'error_code'       => 'MerchantNotDefined',
                    'error_message'    => 'Uye isyeri kullanici tanimi bulunamadi.',
                    'status'           => 'declined',
                    'status_detail'    => 'MerchantNotDefined',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'order_not_found'         => [
                'input'    => [
                    'VPosOrderData'   => '',
                    'ResponseCode'    => 'NonResult',
                    'ResponseMessage' => 'Kriterlere uygun sonuc bulunmamaktadir.',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expected' => [
                    'proc_return_code' => 'NonResult',
                    'order_id'         => null,
                    'remote_order_id'  => null,
                    'error_code'       => 'NonResult',
                    'error_message'    => 'Kriterlere uygun sonuc bulunmamaktadir.',
                    'status'           => 'declined',
                    'status_detail'    => 'NonResult',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'success_pay_then_cancel' => [
                'input'    => [
                    'VPosOrderData'   => [
                        'OrderContract' => [
                            [
                                'OrderId'                        => '6373641',
                                'MerchantOrderId'                => '202407019FDB',
                                'MerchantId'                     => '1',
                                'PosTerminalId'                  => '111111',
                                'OrderStatus'                    => '1',
                                'OrderStatusDescription'         => 'Satis',
                                'OrderType'                      => '2',
                                'OrderTypeDescription'           => 'Taksitli',
                                'TransactionStatus'              => '1',
                                'TransactionStatusDescription'   => 'Basarili',
                                'LastOrderStatus'                => '6',
                                'LastOrderStatusDescription'     => 'Iptal',
                                'EndOfDayStatus'                 => '1',
                                'EndOfDayStatusDescription'      => 'Acik',
                                'FEC'                            => '0949',
                                'FecDescription'                 => 'TRY',
                                'TransactionSecurity'            => '3',
                                'TransactionSecurityDescription' => '3d islem',
                                'CardHolderName'                 => 'john doe',
                                'CardType'                       => 'MasterCard',
                                'CardNumber'                     => '5351********9885',
                                'OrderDate'                      => '2024-07-01T15:21:28.123',
                                'FirstAmount'                    => '10.01',
                                'TranAmount'                     => '10.01',
                                'FECAmount'                      => '0.00',
                                'CancelAmount'                   => '10.01',
                                'DrawbackAmount'                 => '0.00',
                                'ClosedAmount'                   => '0.00',
                                'InstallmentCount'               => '2',
                                'ResponseCode'                   => '00',
                                'ResponseExplain'                => 'İşlem onaylandı',
                                'ProvNumber'                     => '520366',
                                'RRN'                            => '418315158962',
                                'Stan'                           => '435438',
                                'MerchantUserName'               => 'apiuser',
                                'BatchId'                        => '1',
                            ],
                            [
                                'OrderId'                        => '6373641',
                                'MerchantOrderId'                => '202407019FDB',
                                'MerchantId'                     => '1',
                                'PosTerminalId'                  => '111111',
                                'OrderStatus'                    => '6',
                                'OrderStatusDescription'         => 'Iptal',
                                'OrderType'                      => '2',
                                'OrderTypeDescription'           => 'Taksitli',
                                'TransactionStatus'              => '1',
                                'TransactionStatusDescription'   => 'Basarili',
                                'LastOrderStatus'                => '6',
                                'LastOrderStatusDescription'     => 'Iptal',
                                'EndOfDayStatus'                 => '1',
                                'EndOfDayStatusDescription'      => 'Acik',
                                'FEC'                            => '0949',
                                'FecDescription'                 => 'TRY',
                                'TransactionSecurity'            => '3',
                                'TransactionSecurityDescription' => '3d islem',
                                'CardHolderName'                 => 'john doe',
                                'CardType'                       => 'MasterCard',
                                'CardNumber'                     => '5351********9885',
                                'OrderDate'                      => '2024-07-01T15:22:24.463',
                                'FirstAmount'                    => '10.01',
                                'TranAmount'                     => '10.01',
                                'FECAmount'                      => '0.00',
                                'CancelAmount'                   => '10.01',
                                'DrawbackAmount'                 => '0.00',
                                'ClosedAmount'                   => '0.00',
                                'InstallmentCount'               => '2',
                                'ResponseCode'                   => '00',
                                'ResponseExplain'                => 'İşlem onaylandı',
                                'ProvNumber'                     => '520366',
                                'RRN'                            => '418315158962',
                                'Stan'                           => '435440',
                                'MerchantUserName'               => 'apiuser',
                                'BatchId'                        => '1',
                            ],
                        ],
                    ],
                    'ResponseCode'    => '00',
                    'ResponseMessage' => '',
                    '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expected' => [
                    'proc_return_code' => '00',
                    'order_id'         => '202407019FDB',
                    'remote_order_id'  => '6373641',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'         => '520366',
                            'capture'           => true,
                            'capture_amount'    => 10.01,
                            'currency'          => 'TRY',
                            'error_code'        => null,
                            'error_message'     => null,
                            'first_amount'      => 10.01,
                            'installment_count' => 2,
                            'masked_number'     => '5351********9885',
                            'order_status'      => PosInterface::PAYMENT_STATUS_CANCELED,
                            'payment_model'     => '3d',
                            'proc_return_code'  => '00',
                            'ref_ret_num'       => '418315158962',
                            'status'            => 'approved',
                            'status_detail'     => 'approved',
                            'transaction_id'    => '435438',
                            'transaction_type'  => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-07-01T15:21:28.123'),
                            'capture_time'      => new \DateTimeImmutable('2024-07-01T15:21:28.123'),
                        ],
                        [
                            'auth_code'         => '520366',
                            'capture'           => null,
                            'capture_amount'    => null,
                            'currency'          => 'TRY',
                            'error_code'        => null,
                            'error_message'     => null,
                            'first_amount'      => 10.01,
                            'installment_count' => 2,
                            'masked_number'     => '5351********9885',
                            'order_status'      => PosInterface::PAYMENT_STATUS_CANCELED,
                            'payment_model'     => '3d',
                            'proc_return_code'  => '00',
                            'ref_ret_num'       => '418315158962',
                            'status'            => 'approved',
                            'status_detail'     => 'approved',
                            'transaction_id'    => '435440',
                            'transaction_type'  => null,
                            'transaction_time'  => new \DateTimeImmutable('2024-07-01T15:22:24.463'),
                            'cancel_time'       => new \DateTimeImmutable('2024-07-01T15:22:24.463'),
                            'capture_time'      => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'ResponseCode'    => '00',
                    'ResponseMessage' => '',
                    'ProvisionNumber' => 'prov-123',
                    'MerchantOrderId' => '15161',
                    'OrderId'         => 'o-123',
                    'RRN'             => '904115005554',
                    'Stan'            => '005554',
                    'HashData'        => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
                    'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                ],
                'expectedData' => [
                    'amount'               => null,
                    'auth_code'            => 'prov-123',
                    'currency'             => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'order_id'             => '15161',
                    'payment_model'        => '3d_host',
                    'proc_return_code'     => '00',
                    'ref_ret_num'          => '904115005554',
                    'batch_num'            => null,
                    'remote_order_id'      => 'o-123',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'transaction_id'       => '005554',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'transaction_type'     => 'pay',
                    'transaction_security' => null,
                ],
            ],
            'fail'    => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'ResponseCode'    => '05',
                    'ResponseMessage' => 'error abcd',
                    'MerchantOrderId' => '15161',
                    'OrderId'         => 'o-123',
                ],
                'expectedData' => [
                    'amount'               => null,
                    'auth_code'            => null,
                    'currency'             => null,
                    'error_code'           => '05',
                    'error_message'        => 'error abcd',
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'order_id'             => null,
                    'payment_model'        => '3d_host',
                    'proc_return_code'     => '05',
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'status'               => 'declined',
                    'status_detail'        => '05',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                ],
            ],
        ];
    }
}
