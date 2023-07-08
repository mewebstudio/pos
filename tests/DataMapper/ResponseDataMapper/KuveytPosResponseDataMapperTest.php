<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\KuveytPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class KuveytPosResponseDataMapperTest extends TestCase
{
    /** @var KuveytPosResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestDataMapper        = new KuveytPosRequestDataMapper($crypt);
        $this->responseDataMapper = new KuveytPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
    }

    public function testAmountFormat()
    {
        $this->assertEquals(0.1, $this->responseDataMapper::amountFormat("10"));
        $this->assertEquals(1.01, $this->responseDataMapper::amountFormat("101"));
    }

    /**
     * @dataProvider paymentTestDataProvider
     */
    public function testMapPaymentResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData);
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
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $threeDResponseData, array $paymentResponse, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DPaymentData($threeDResponseData, $paymentResponse);
        unset($actualData['all']);
        unset($actualData['3d_all']);
        $this->assertSame($expectedData, $actualData);
    }

    public static function paymentTestDataProvider(): array
    {
        return
            [
                //fail case
                [
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
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => 'MetaDataNotFound',
                        'status'           => 'declined',
                        'status_detail'    => 'MetaDataNotFound',
                        'error_code'       => 'MetaDataNotFound',
                        'error_message'    => 'Ödeme detayı bulunamadı.',
                    ],
                ],
            ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'authSuccessPaymentFail1' => [
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
                    'amount'               => '10',
                    'currency'             => 'TRY',
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
                ],
            ],
            'authSuccessPaymentFail2' => [
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
                        'CardNumber'          => '4025502306586032',
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
                    'amount'               => '100',
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'md_error_message'     => null,
                    'masked_number'        => '4025502306586032',
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'error_message'        => 'Geçerli bir MD değeri giriniz.',
                    'order_id'             => 'Order 123',
                    'proc_return_code'     => 'EmptyMDException',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'error_code'           => 'EmptyMDException',
                ],
            ],
            'authFail1'               => [
                // 3D Auth fail case
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
                ],
            ],
            'success1'                => [
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
                        'CardNumber'          => '4025502306586032',
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
                        'CardNumber'          => '4025502306586032',
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
                    'order_id'             => '660723214',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'amount'               => 1.0,
                    'currency'             => 'TRY',
                    'error_code'           => null,
                    'masked_number'        => '4025502306586032',
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
                'capture'          => false,
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
                            'OrderId'             => 114293600,
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
                'error_message'    => null,
                'ref_ret_num'      => '318923298433',
                'order_status'     => 1,
                'transaction_type' => null,
                'masked_number'    => '518896******2544',
                'first_amount'     => 1.01,
                'capture_amount'   => 1.01,
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
                'capture'          => false,
                'currency'         => 'TRY',
                'date'             => '2023-07-08T23:45:15.797',
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
                'currency'         => 'TRY',
                'error_message'    => null,
                'ref_ret_num'      => '318923298433',
                'status'           => 'approved',
                'error_code'       => null,
                'status_detail'    => null,
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
                'order_id' => null,
                'auth_code' => null,
                'proc_return_code' => null,
                'trans_id' => null,
                'currency' => null,
                'error_message' => null,
                'ref_ret_num' => null,
                'status' => 'declined',
                'error_code' => null,
                'status_detail' => null,
            ],
        ];
    }
}
