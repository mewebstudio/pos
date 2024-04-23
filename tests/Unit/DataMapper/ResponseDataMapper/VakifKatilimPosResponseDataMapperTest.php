<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper
 */
class VakifKatilimPosResponseDataMapperTest extends TestCase
{
    private VakifKatilimPosResponseDataMapper $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = CryptFactory::createGatewayCrypt(VakifKatilimPos::class, new NullLogger());
        $requestDataMapper        = new VakifKatilimPosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);
        $this->responseDataMapper = new VakifKatilimPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            new NullLogger()
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
        unset($actualData['all'], $actualData['3d_all']);
        $this->assertEquals($expectedData, $actualData);
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
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelTestDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);
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

        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
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
            unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
            unset($actualData['transactions'][$key]['capture_time'], $expectedData['transactions'][$key]['capture_time']);
            \ksort($actualData['transactions'][$key]);
            \ksort($expectedData['transactions'][$key]);
        }

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
                    'ResponseMessage' => '',
                    'ProvisionNumber' => '',
                    'MerchantOrderId' => '15161',
                    'OrderId'         => '0',
                    'RRN'             => '904115005554',
                    'Stan'            => '005554',
                    'HashData'        => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
                    'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                ],
                'paymentData'        => [
                    'VPosMessageContract' => [
                        'OkUrl'                            => 'http://localhost/ThreeDModel/Approval',
                        'FailUrl'                          => 'http://localhost/ThreeDModel/Fail',
                        'HashData'                         => 'DvAUXMvYV4ex5m16mMezEl+kxrI=',
                        'MerchantId'                       => '1',
                        'SubMerchantId'                    => '0',
                        'CustomerId'                       => '936',
                        'UserName'                         => 'APIUSER',
                        'HashPassword'                     => 'kfkdsnskslkclswr9430ır',
                        'MerchantOrderId'                  => '1554891870',
                        'InstallmentCount'                 => '0',
                        'Amount'                           => '111',
                        'FECAmount'                        => '0',
                        'AdditionalData'                   => [
                            'AdditionalDataList' => [
                                'VPosAdditionalData' => [
                                    'Key'  => 'MD',
                                    'Data' => 'vygnTBD4smBxAOlDsgbaOQ==',
                                ],
                            ],
                        ],
                        'Products'                         => '',
                        'Addresses'                        => '',
                        'PaymentType'                      => '1',
                        'DebtId'                           => '0',
                        'SurchargeAmount'                  => '0',
                        'SGKDebtAmount'                    => '0',
                        'InstallmentMaturityCommisionFlag' => '0',
                        'TransactionSecurity'              => '3',
                    ],
                    'IsEnrolled'          => 'true',
                    'IsVirtual'           => 'false',
                    'RRN'                 => '922709016599',
                    'Stan'                => '016599',
                    'ResponseCode'        => '00',
                    'ResponseMessage'     => 'Provizyon Alindi.',
                    'OrderId'             => '15161',
                    'TransactionTime'     => '00010101T00:00:00',
                    'MerchantOrderId'     => '1554891870',
                    'HashData'            => 'bcCqBe4hbElPOVYtfvsw7M44usQ=',
                    '@xmlns:xsi'          => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd'          => 'http://www.w3.org/2001/XMLSchema',
                ],
                'expectedData'       => [
                    'transaction_security' => null,
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'transaction_id'       => '016599',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                    'auth_code'            => null,
                    'ref_ret_num'          => '922709016599',
                    'batch_num'            => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'remote_order_id'      => '15161',
                    'order_id'             => '1554891870',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 1.11,
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
                    'ResponseCode'    => '05',
                    'ResponseMessage' => '',
                    'ProvisionNumber' => '',
                    'MerchantOrderId' => '15161',
                    'OrderId'         => '0',
                    'RRN'             => '',
                    'Stan'            => '',
                    'HashData'        => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
                    'MD'              => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_security' => null,
                    'md_status'            => null,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'error_code'           => '05',
                    'error_message'        => null,
                    'order_id'             => '15161',
                    'proc_return_code'     => '05',
                    'status'               => 'declined',
                    'status_detail'        => '05',
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
                'order_status'      => null,
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
    }

    public static function cancelTestDataProvider(): iterable
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
                    'BatchId'                          => '',
                    'MerchantOrderId'                  => '2023070849CD',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '100',
                    'DisplayAmount'                    => '100',
                    'FECAmount'                        => '',
                    'FECCurrencyCode'                  => '0949',
                    'Addresses'                        => [
                        'VPosAddressContract' => [
                            'Type'        => '',
                            'Name'        => '',
                            'PhoneNumber' => '',
                            'OrderId'     => '',
                            'AddressId'   => '',
                            'Email'       => '',
                        ],
                    ],
                    'APIVersion'                       => '1.0.0',
                    'PaymentType'                      => '',
                    'SurchargeAmount'                  => '',
                    'SGKDebtAmount'                    => '',
                    'InstallmentMaturityCommisionFlag' => '',
                    'TransactionSecurity'              => '',
                ],
                'RRN'             => '904115005554',
                'Stan'            => '005554',
                'IsEnrolled'      => 'false',
                'IsVirtual'       => 'false',
                'ResponseCode'    => '00',
                'ResponseMessage' => 'OTORİZASYON VERİLDİ',
                'OrderId'         => '114293600',
                'TransactionTime' => '2023-07-08T23:45:15.797',
                'BusinessKey'     => '202208456498416947',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
            'expectedData' => [
                'order_id'         => '2023070849CD',
                'auth_code'        => null,
                'proc_return_code' => '00',
                'transaction_id'   => '005554',
                'currency'         => PosInterface::CURRENCY_TRY,
                'error_message'    => null,
                'ref_ret_num'      => '904115005554',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293600',
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
                'currency'         => PosInterface::CURRENCY_TRY,
                'error_message'    => null,
                'ref_ret_num'      => '904115005554',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293600',
            ],
        ];
    }

    public static function historyTestDataProvider(): array
    {
        return [
            [
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
                            'order_status'      => 'Satis',
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
        ];
    }

    public static function orderHistoryTestDataProvider(): array
    {
        return [
            'fail1'    => [
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
            'success1' => [
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
                                'OrderId'                        => '12754',
                                'MerchantOrderId'                => '709834990',
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
                    'order_id'         => '709834990',
                    'remote_order_id'  => '12754',
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
                            'order_status'      => 'Satis',
                            'transaction_type'  => null,
                            'first_amount'      => 2.7,
                            'capture_amount'    => 0,
                            'status'            => 'approved',
                            'error_code'        => null,
                            'status_detail'     => 'approved',
                            'capture'           => false,
                            'currency'          => 'TRY',
                            'masked_number'     => '5353********8906',
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
                            'payment_model'    => 'regular',
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
