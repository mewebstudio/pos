<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper
 */
class KuveytPosResponseDataMapperTest extends TestCase
{
    private KuveytPosResponseDataMapper $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = CryptFactory::createGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestDataMapper        = new KuveytPosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);
        $this->responseDataMapper = new KuveytPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            new NullLogger()
        );
    }

    /**
     * @return void
     */
    public function testFormatAmount()
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
    public function testMapPaymentResponse(string $txType, array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, []);
        unset($actualData['all']);
        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundTestDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelTestDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        $this->assertEquals($expectedData['trans_time'], $actualData['trans_time']);
        $this->assertEquals($expectedData['capture_time'], $actualData['capture_time']);
        unset($actualData['trans_time'], $expectedData['trans_time']);
        unset($actualData['capture_time'], $expectedData['capture_time']);

        unset($actualData['all']);
        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $paymentResponse, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order
        );
        unset($actualData['all'], $actualData['3d_all']);
        $this->assertEquals($expectedData, $actualData);
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
                'order_id'         => null,
                'trans_id'         => null,
                'transaction_type' => 'pay',
                'currency'         => null,
                'amount'           => null,
                'payment_model'    => 'regular',
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => 'MetaDataNotFound',
                'status'           => 'declined',
                'status_detail'    => 'MetaDataNotFound',
                'error_code'       => 'MetaDataNotFound',
                'error_message'    => 'Ödeme detayı bulunamadı.',
                'installment'      => null,
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
                'order_id'         => '660723214',
                'trans_id'         => '005554',
                'transaction_type' => 'pay',
                'currency'         => 'TRY',
                'amount'           => 1.0,
                'payment_model'    => 'regular',
                'auth_code'        => '896626',
                'ref_ret_num'      => '904115005554',
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
                'remote_order_id'  => '4480',
                'masked_number'    => '5124********1609',
                'installment'      => 0,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'authSuccessPaymentFail1' => [
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
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => 0.1,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => '5124********1609',
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'error_message'        => 'Ödeme detayı bulunamadı.',
                    'order_id'             => 'MP-15',
                    'proc_return_code'     => 'MetaDataNotFound',
                    'status'               => 'declined',
                    'status_detail'        => 'MetaDataNotFound',
                    'error_code'           => 'MetaDataNotFound',
                    'payment_model'        => '3d',
                    'transaction_type'     => 'pay',
                    'installment'          => null,
                ],
            ],
            'authSuccessPaymentFail2' => [
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
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => 1.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => '5124********1609',
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'error_message'        => 'Geçerli bir MD değeri giriniz.',
                    'order_id'             => 'Order 123',
                    'proc_return_code'     => 'EmptyMDException',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'error_code'           => 'EmptyMDException',
                    'payment_model'        => '3d',
                    'transaction_type'     => 'pay',
                    'installment'          => null,
                ],
            ],
            'authFail1'               => [
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
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => 'HashDataError',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'error_code'           => 'HashDataError',
                    'error_message'        => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => null,
                    'amount'               => null,
                    'currency'             => null,
                    'tx_status'            => null,
                    'md_error_message'     => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                    'payment_model'        => null,
                    'transaction_type'     => 'pay',
                    'installment'          => null,
                ],
            ],
            'success1'                => [
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
                    'trans_id'             => '005554',
                    'auth_code'            => '896626',
                    'ref_ret_num'          => '904115005554',
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
                    'transaction_type'     => 'pay',
                    'installment'          => 0,
                ],
            ],
        ];
    }

    public static function statusTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
                'GetMerchantOrderDetailResult' => [
                    'Results' => [],
                    'Success' => true,
                    'Value'   => [],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'trans_id'         => null,
                'error_message'    => null,
                'ref_ret_num'      => null,
                'order_status'     => null,
                'transaction_type' => null,
                'masked_number'    => null,
                'first_amount'     => null,
                'capture_amount'   => null,
                'status'           => 'declined',
                'error_code'       => null,
                'status_detail'    => null,
                'capture'          => null,
                'capture_time'     => null,
                'trans_time'       => null,
                'currency'         => null,
            ],
        ];
        yield 'success1' => [
            'responseData' => [
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
            'expectedData' => [
                'order_id'         => '2023070849CD',
                'auth_code'        => '241839',
                'proc_return_code' => '00',
                'trans_id'         => '298433',
                'ref_ret_num'      => '318923298433',
                'order_status'     => 'PAYMENT_COMPLETED',
                'transaction_type' => null,
                'masked_number'    => '518896******2544',
                'first_amount'     => 1.01,
                'capture_amount'   => 1.01,
                'status'           => 'approved',
                'error_code'       => null,
                'error_message'    => null,
                'status_detail'    => null,
                'capture'          => true,
                'remote_order_id'  => '114293600',
                'currency'         => PosInterface::CURRENCY_TRY,
                'capture_time'     => null,
                'trans_time'       => new \DateTime('2023-07-08T23:45:15.797'),
            ],
        ];
    }

    public static function cancelTestDataProvider(): iterable
    {
        yield 'success1' => [
            'responseData' => [
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
            'expectedData' => [
                'order_id'         => '2023070849CD',
                'auth_code'        => '241839',
                'proc_return_code' => '00',
                'trans_id'         => '298433',
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
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'trans_id'         => null,
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => null,
                'status_detail'    => null,
            ],
        ];
        yield 'fail2' => [
            'responseData' => [
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
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'trans_id'         => null,
                'currency'         => null,
                'error_message'    => 'İşlem daha önce iptal edilmiştir.',
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => '21',
                'status_detail'    => null,
            ],
        ];
    }

    public static function refundTestDataProvider(): iterable
    {
        yield 'fail1' => [
            'responseData' => [
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
            'expectedData' => [
                'order_id'         => '202307093C2D',
                'auth_code'        => null,
                'proc_return_code' => '28',
                'trans_id'         => '298460',
                'currency'         => PosInterface::CURRENCY_TRY,
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
            'expectedData' => [
                'order_id'         => null,
                'auth_code'        => null,
                'proc_return_code' => null,
                'trans_id'         => null,
                'currency'         => null,
                'error_message'    => null,
                'ref_ret_num'      => null,
                'status'           => 'declined',
                'error_code'       => null,
                'status_detail'    => null,
            ],
        ];

        yield 'success1' => [
            'responseData' => [
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
            'expectedData' => [
                'order_id'         => '202307091285',
                'auth_code'        => '241859',
                'proc_return_code' => '00',
                'trans_id'         => '298463',
                'currency'         => PosInterface::CURRENCY_TRY,
                'error_message'    => null,
                'ref_ret_num'      => '319014298463',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'remote_order_id'  => '114293626',
            ],
        ];
    }
}
