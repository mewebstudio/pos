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

        $crypt = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $requestDataMapper  = new KuveytPosRequestDataMapper($crypt);
        $this->responseDataMapper = new KuveytPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
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
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $threeDResponseData, array $paymentResponse, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DPaymentData($threeDResponseData, $paymentResponse);
        unset($actualData['all']);
        unset($actualData['3d_all']);
        $this->assertSame($expectedData, $actualData);
    }

    public function paymentTestDataProvider(): array
    {
        return
            [
                //fail case
                [
                    'responseData' => [
                        '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                        '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                        'IsEnrolled' => 'true',
                        'IsVirtual' => 'false',
                        'ResponseCode' => 'MetaDataNotFound',
                        'ResponseMessage' => 'Ödeme detayı bulunamadı.',
                        'OrderId' => '0',
                        'TransactionTime' => '0001-01-01T00:00:00',
                        'BusinessKey' => '0',
                    ],
                    'expectedData'  => [
                        'order_id' => null,
                        'trans_id' => null,
                        'auth_code' => null,
                        'ref_ret_num' => null,
                        'proc_return_code' => 'MetaDataNotFound',
                        'status' => 'declined',
                        'status_detail' => 'MetaDataNotFound',
                        'error_code' => 'MetaDataNotFound',
                        'error_message' => 'Ödeme detayı bulunamadı.',
                    ],
                ],
            ];
    }


    public function threeDPaymentDataProvider(): array
    {
        return [
            'authSuccessPaymentFail1' => [
                'threeDResponseData' => [
                    '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                    'VPosMessage' => [
                        'OrderId' => '86483278',
                        'OkUrl' => 'https://www.example.com/testodeme',
                        'FailUrl' => 'https://www.example.com/testodeme',
                        'MerchantId' => '48544',
                        'SubMerchantId' => '0',
                        'CustomerId' => '123456',
                        'UserName' => 'fapapi',
                        'HashPassword' => 'Hiorgg24rNeRdHUvMCg//mOJn4U=',
                        'CardNumber' => '5124********1609',
                        'BatchID' => '1576',
                        'InstallmentCount' => '0',
                        'Amount' => '10',
                        'CancelAmount' => '0',
                        'MerchantOrderId' => 'MP-15',
                        'FECAmount' => '0',
                        'CurrencyCode' => '949',
                        'QeryId' => '0',
                        'DebtId' => '0',
                        'SurchargeAmount' => '0',
                        'SGKDebtAmount' => '0',
                        'TransactionSecurity' => '3',
                        'DeferringCount' => [
                            '@xsi:nil' => 'true',
                            '#' => '',
                        ],
                        'InstallmentMaturityCommisionFlag' => '0',
                        'PaymentId' => [
                            '@xsi:nil' => 'true',
                            '#' => '',
                        ],
                        'OrderPOSTransactionId' => [
                            '@xsi:nil' => 'true',
                            '#' => '',
                        ],
                        'TranDate' => [
                            '@xsi:nil' => 'true',
                            '#' => '',
                        ],
                        'TransactionUserId' => [
                            '@xsi:nil' => 'true',
                            '#' => '',
                        ],
                    ],
                    'IsEnrolled' => 'true',
                    'IsVirtual' => 'false',
                    'ResponseCode' => '00',
                    'ResponseMessage' => 'Kart doğrulandı.',
                    'OrderId' => '86483278',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => 'MP-15',
                    'HashData' => 'mOw0JGvy1JVWqDDmFyaDTvKz9Fk=',
                    'MD' => 'ktSVkYJHcHSYM1ibA/nM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz',
                    'BusinessKey' => '202208456498416947',
                ],
                'paymentData' => [
                    '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                    'IsEnrolled' => 'true',
                    'IsVirtual' => 'false',
                    'ResponseCode' => 'MetaDataNotFound',
                    'ResponseMessage' => 'Ödeme detayı bulunamadı.',
                    'OrderId' => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'BusinessKey' => '0',
                ],
                'expectedData' => [
                    'transaction_security' => 'MPI fallback',
                    'md_status' => null,
                    'amount' => '10',
                    'currency' => 'TRY',
                    'tx_status' => null,
                    'md_error_message' => null,
                    'masked_number' => '5124********1609',
                    'trans_id' => null,
                    'auth_code' => null,
                    'ref_ret_num' => null,
                    'error_message' => 'Ödeme detayı bulunamadı.',
                    'order_id' => 'MP-15',
                    'proc_return_code' => 'MetaDataNotFound',
                    'status' => 'declined',
                    'status_detail' => 'MetaDataNotFound',
                    'error_code' => 'MetaDataNotFound',
                ],
            ],
            'authSuccessPaymentFail2' => [
                'threeDResponseData' => [
                    'VPosMessage' => [
                        'APIVersion' => '1.0.0',
                        'OkUrl' => 'http://localhost:44785/Home/Success',
                        'FailUrl' => 'http://localhost:44785/Home/Fail',
                        'HashData' => 'lYJYMi/gVO9MWr32Pshaa/zAbSHY=',
                        'MerchantId' => '80',
                        'SubMerchantId' => '0',
                        'CustomerId' => '400235',
                        'UserName' => 'apiuser',
                        'CardNumber' => '4025502306586032',
                        'CardHolderName' => 'afafa',
                        'CardType' => 'MasterCard',
                        'BatchID' => '0',
                        'TransactionType' => 'Sale',
                        'InstallmentCount' => '0',
                        'Amount' => '100',
                        'DisplayAmount' => '100',
                        'MerchantOrderId' => 'Order 123',
                        'FECAmount' => '0',
                        'CurrencyCode' => '0949',
                        'QeryId' => '0',
                        'DebtId' => '0',
                        'SurchargeAmount' => '0',
                        'SGKDebtAmount' => '0',
                        'TransactionSecurity' => '3',
                        'TransactionSide' => 'Auto',
                        'EntryGateMethod' => 'VPOS_ThreeDModelPayGate'
                    ],
                    'IsEnrolled' => 'true',
                    'IsVirtual' => 'false',
                    'OrderId' => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'ResponseCode' => '00',
                    'ResponseMessage' => 'HATATA',
                    'MD' => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'AuthenticationPacket' => 'WYGDgSIrSHDtYwF/WEN+nfwX63sppA=',
                    'ACSURL' => 'https://acs.bkm.com.tr/mdpayacs/pareq',
                ],
                'paymentData' => [
                    'IsEnrolled'      => 'false',
                    'IsVirtual'       => 'false',
                    'ResponseCode'    => 'EmptyMDException',
                    'ResponseMessage' => 'Geçerli bir MD değeri giriniz.',
                    'OrderId'         => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'BusinessKey'     => '0',
                ],
                'expectedData' => [
                    'transaction_security' => 'MPI fallback',
                    'md_status' => null,
                    'amount' => '100',
                    'currency' => 'TRY',
                    'tx_status' => null,
                    'md_error_message' => null,
                    'masked_number' => '4025502306586032',
                    'trans_id' => null,
                    'auth_code' => null,
                    'ref_ret_num' => null,
                    'error_message' => 'Geçerli bir MD değeri giriniz.',
                    'order_id' => 'Order 123',
                    'proc_return_code' => 'EmptyMDException',
                    'status' => 'declined',
                    'status_detail' => 'invalid_transaction',
                    'error_code' => 'EmptyMDException',
                ],
            ],
            'authFail1' => [
                // 3D Auth fail case
                'threeDResponseData' => [
                    '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                    'IsEnrolled' => 'true',
                    'IsVirtual' => 'false',
                    'ResponseCode' => 'HashDataError',
                    'ResponseMessage' => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                    'OrderId' => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'MerchantOrderId' => '2020110828BC',
                    'ReferenceId' => '9b8e2326a9df44c2b2aac0b98b11f0a4',
                    'BusinessKey' => '0',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id' => '2020110828BC',
                    'trans_id' => null,
                    'auth_code' => null,
                    'ref_ret_num' => null,
                    'proc_return_code' => 'HashDataError',
                    'status' => 'declined',
                    'status_detail' => 'invalid_transaction',
                    'error_code' => 'HashDataError',
                    'error_message' => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status' => null,
                    'amount' => null,
                    'currency' => null,
                    'tx_status' => null,
                    'md_error_message' => 'Şifrelenen veriler (Hashdata) uyuşmamaktadır.',
                ],
            ],
            'success1' => [
                'threeDResponseData' => [
                    'VPosMessage' => [
                        'APIVersion' => '1.0.0',
                        'OkUrl' => 'http://localhost:44785/Home/Success',
                        'FailUrl' => 'http://localhost:44785/Home/Fail',
                        'HashData' => 'lYJYMi/gVO9MWr32Pshaa/zAbSHY=',
                        'MerchantId' => '80',
                        'SubMerchantId' => '0',
                        'CustomerId' => '400235',
                        'UserName' => 'apiuser',
                        'CardNumber' => '4025502306586032',
                        'CardHolderName' => 'afafa',
                        'CardType' => 'MasterCard',
                        'BatchID' => '0',
                        'TransactionType' => 'Sale',
                        'InstallmentCount' => '0',
                        'Amount' => '100',
                        'DisplayAmount' => '100',
                        'MerchantOrderId' => 'Order 123',
                        'FECAmount' => '0',
                        'CurrencyCode' => '0949',
                        'QeryId' => '0',
                        'DebtId' => '0',
                        'SurchargeAmount' => '0',
                        'SGKDebtAmount' => '0',
                        'TransactionSecurity' => '3',
                        'TransactionSide' => 'Auto',
                        'EntryGateMethod' => 'VPOS_ThreeDModelPayGate',
                    ],
                    'IsEnrolled' => 'true',
                    'IsVirtual' => 'false',
                    'OrderId' => '0',
                    'TransactionTime' => '0001-01-01T00:00:00',
                    'ResponseCode' => '00',
                    'ResponseMessage' => 'HATATA',
                    'MD' => '67YtBfBRTZ0XBKnAHi8c/A==',
                    'AuthenticationPacket' => 'WYGDgSIrSHDtYwF/WEN+nfwX63sppA=',
                    'ACSURL' => 'https://acs.bkm.com.tr/mdpayacs/pareq',
                ],
                'paymentData' => [
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
                'expectedData' => [
                    'transaction_security' => 'MPI fallback',
                    'md_status' => null,
                    'tx_status' => null,
                    'md_error_message' => null,
                    'trans_id' => null,
                    'auth_code' => '896626',
                    'ref_ret_num' => '904115005554',
                    'error_message' => null,
                    'order_id' => '660723214',
                    'proc_return_code' => '00',
                    'status' => 'approved',
                    'status_detail' => 'approved',
                    'amount' => '100',
                    'currency' => 'TRY',
                    'error_code' => null,
                    'masked_number' => '4025502306586032',
                ]
            ]
        ];
    }
}
