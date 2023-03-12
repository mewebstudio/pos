<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VakifBankPosResponseDataMapperTest extends TestCase
{
    /** @var VakifBankPosResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $requestDataMapper  = new VakifBankPosRequestDataMapper();
        $this->responseDataMapper = new VakifBankPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $threeDResponseData, array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPaymentData($threeDResponseData, $paymentResponse);
        unset($actualData['all']);
        unset($actualData['3d_all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider paymentDataProvider
     */
    public function testMapPaymentResponse(array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($paymentResponse);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testMapCancelResponse(array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($paymentResponse);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testMapRefundResponse(array $paymentResponse, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($paymentResponse);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    public static function refundDataProvider(): iterable
    {
        yield 'success_1' => [
            'responseData' => [
                'MerchantId' => '000100000013506',
                'TransactionType' => 'Refund',
                'TransactionId' => '455ae6c09140434ea6edafc0018acbb9',
                'ReferenceTransactionId' => '6d491ea480564068976fafc0018a9def',
                'ResultCode' => '0000',
                'ResultDetail' => 'İŞLEM BAŞARILI',
                'InstallmentTable' => null,
                'CampaignResult' => null,
                'AuthCode' => '752800',
                'HostDate' => '20230309235724',
                'Rrn' => '306823971382',
                'TerminalNo' => 'VP000579',
                'CurrencyAmount' => '1.01',
                'CurrencyCode' => '949',
                'BatchNo' => '300',
                'TLAmount' => '1.01',
            ],
            'expectedData' => [
                'order_id' => '455ae6c09140434ea6edafc0018acbb9',
                'auth_code' => '752800',
                'ref_ret_num' => '306823971382',
                'proc_return_code' => '0000',
                'trans_id' => '455ae6c09140434ea6edafc0018acbb9',
                'error_code' => null,
                'error_message' => null,
                'status' => 'approved',
                'status_detail' => 'İŞLEM BAŞARILI',
            ],
        ];

        yield 'fail_1' => [
            'responseData' => [
                'ResultCode' => '1059',
                'ResultDetail' => 'İşlemin tamamı iade edilmiş.',
                'InstallmentTable' => null,
            ],
            'expectedData' => [
                'order_id' => null,
                'auth_code' => null,
                'ref_ret_num' => null,
                'proc_return_code' => '1059',
                'trans_id' => null,
                'error_code' => '1059',
                'error_message' => 'İşlemin tamamı iade edilmiş.',
                'status' => 'declined',
                'status_detail' => 'İşlemin tamamı iade edilmiş.'
            ],
        ];
    }

    public static function cancelDataProvider(): iterable
    {
        yield 'success_1' => [
            'responseData' => [
                'MerchantId' => '000100000013506',
                'TransactionType' => 'Cancel',
                'TransactionId' => '4a8e979308de4568b500afc00187a501',
                'ReferenceTransactionId' => '3f30ab117aa74826b448afc0018789fa',
                'ResultCode' => '0000',
                'ResultDetail' => 'İŞLEM BAŞARILI',
                'InstallmentTable' => null,
                'CampaignResult' => null,
                'AuthCode' => '836044',
                'HostDate' => '20230309234556',
                'Rrn' => '306823971363',
                'TerminalNo' => 'VP000579',
                'CurrencyAmount' => '1.01',
                'CurrencyCode' => '949',
                'BatchNo' => '300',
                'TLAmount' => '1.01',
            ],
            'expectedData' => [
                'order_id'         => '4a8e979308de4568b500afc00187a501',
                'auth_code'        => '836044',
                'ref_ret_num'      => '306823971363',
                'proc_return_code' => '0000',
                'trans_id'         => '4a8e979308de4568b500afc00187a501',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => 'İŞLEM BAŞARILI',
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
                'trans_id'         => null,
                'error_code'       => '1083',
                'error_message'    => 'Referans islem daha önceden iptal edilmis.',
                'status'           => 'declined',
                'status_detail'    => 'Referans islem daha önceden iptal edilmis.',
            ],
        ];
    }
    
    public static function paymentDataProvider(): iterable
    {
        yield 'success_1' => [
            'responseData' => [
                'MerchantId' => '000100000013506',
                'TransactionType' => 'Sale',
                'TransactionId' => '9972767117b3400eb2acafc0018643df',
                'ResultCode' => '0000',
                'ResultDetail' => 'İŞLEM BAŞARILI',
                'CustomItems' => [
                    'Item' => [
                        '@name' => 'CardHolderName',
                        '@value' => 'AR* ÖZ*',
                        '#' => null,
                    ],
                ],
                'InstallmentTable' => null,
                'CampaignResult' => null,
                'AuthCode' => '961451',
                'HostDate' => '20230309234054',
                'Rrn' => '306823971358',
                'TerminalNo' => 'VP000579',
                'GainedPoint' => '10.00',
                'TotalPoint' => '103032.52',
                'CurrencyAmount' => '1.01',
                'CurrencyCode' => '949',
                'OrderId' => '202303095646',
                'ThreeDSecureType' => '1',
                'TransactionDeviceSource' => '0',
                'BatchNo' => '300',
                'TLAmount' => '1.01',
            ],
            'expectedData' => [
                'trans_id' => '9972767117b3400eb2acafc0018643df',
                'auth_code' => '961451',
                'ref_ret_num' => '9972767117b3400eb2acafc0018643df',
                'order_id' => '202303095646',
                'eci' => null,
                'proc_return_code' => '0000',
                'status' => 'approved',
                'status_detail' => 'İŞLEM BAŞARILI',
                'error_code' => null,
                'error_message' => null,
                'transaction_type' => 'pay',
            ],
        ];

        yield 'fail_1' => [
            'responseData' => [
                'MerchantId' => '000100000013506',
                'TransactionType' => 'Sale',
                'TransactionId' => '9b47227c275246d39454afc00186dfba',
                'ResultCode' => '0312',
                'ResultDetail' => 'RED-GEÇERSİZ KART',
                'InstallmentTable' => null,
                'CampaignResult' => null,
                'AuthCode' => '000000',
                'HostDate' => '20230309234307',
                'Rrn' => '306823971359',
                'TerminalNo' => 'VP000579',
                'CurrencyAmount' => '1.01',
                'CurrencyCode' => '949',
                'OrderId' => '20230309EF68',
                'ThreeDSecureType' => '1',
                'TransactionDeviceSource' => '0',
                'BatchNo' => '300',
                'TLAmount' => '1.01'
            ],
            'expectedData' => [
                'trans_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'order_id'         => '20230309EF68',
                'eci'              => null,
                'proc_return_code' => '0312',
                'status'           => 'declined',
                'status_detail'    => 'RED-GEÇERSİZ KART',
                'error_code'       => '0312',
                'error_message'    => 'RED-GEÇERSİZ KART',
            ],
        ];
    }
    
    public static function threeDPaymentDataProvider(): array
    {
        return [
            'authFail1' => [
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
                'paymentData' => [],
                'expectedData' => [
                    'eci' => '02',
                    'cavv' => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'md_status' => 'E',
                    'md_error_message' => 'Üye isyeri IP si sistemde tanimli degil',
                    'transaction_security' => null,
                    'trans_id' => null,
                    'ref_ret_num' => null,
                    'proc_return_code' => null,
                    'auth_code' => null,
                    'order_id' => 'order-id-123',
                    'status' => 'declined',
                    'status_detail' => null,
                    'error_code' => '1105',
                    'error_message' => 'Üye isyeri IP si sistemde tanimli degil',
                ],
            ],
            'auth_success_payment_fail' => [
                'threeDResponseData' => [
                    'MerchantId' => '000100000013506',
                    'Pan' => '4938460158754205',
                    'Expiry' => '2411',
                    'PurchAmount' => '101',
                    'PurchCurrency' => '949',
                    'VerifyEnrollmentRequestId' => 'ce06048a3e9c0cd1d437803fb38b5ad0',
                    'Xid' => 'ondg8d9t5besgt88sk8h',
                    'SessionInfo' => 'jpf58sdjj8p9mpb9shurh47v64',
                    'Status' => 'Y',
                    'Cavv' => 'ABIBCBgAAAEnAAABAQAAAAAAAAA=',
                    'Eci' => '05',
                    'ExpSign' => null,
                    'InstallmentCount' => null,
                    'SubMerchantNo' => null,
                    'SubMerchantName' => null,
                    'SubMerchantNumber' => null,
                    'ErrorCode' => null,
                    'ErrorMessage' => null,
                ],
                'paymentData' => [
                    'MerchantId' => '000100000013506',
                    'TransactionType' => 'Sale',
                    'TransactionId' => '202303091489',
                    'ResultCode' => '0312',
                    'ResultDetail' => 'RED-GEÇERSİZ KART',
                    'InstallmentTable' => null,
                    'CampaignResult' => null,
                    'AuthCode' => '000000',
                    'HostDate' => '20230309235359',
                    'Rrn' => '306823971380',
                    'TerminalNo' => 'VP000579',
                    'CurrencyAmount' => '1.01',
                    'CurrencyCode' => '949',
                    'OrderId' => '202303091489',
                    'ECI' => '05',
                    'ThreeDSecureType' => '2',
                    'TransactionDeviceSource' => '0',
                    'BatchNo' => '300',
                    'TLAmount' => '1.01',
                ],
                'expectedData' => [
                    'cavv' => 'ABIBCBgAAAEnAAABAQAAAAAAAAA=',
                    'md_status' => 'Y',
                    'md_error_message' => null,
                    'transaction_security' => null,
                    'trans_id' => null,
                    'ref_ret_num' => null,
                    'proc_return_code' => '0312',
                    'eci' => '05',
                    'auth_code' => null,
                    'order_id' => '202303091489',
                    'status' => 'declined',
                    'status_detail' => 'RED-GEÇERSİZ KART',
                    'error_code' => '0312',
                    'error_message' => 'RED-GEÇERSİZ KART',
                ],
            ],
            'success1' => [
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
                'paymentData' => [
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
                'expectedData' => [
                    'cavv' => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'md_status' => 'Y',
                    'md_error_message' => null,
                    'transaction_security' => null,
                    'trans_id' => '20230309B838',
                    'ref_ret_num' => '20230309B838',
                    'proc_return_code' => '0000',
                    'transaction_type' => 'pay',
                    'eci' => '02',
                    'auth_code' => '822641',
                    'order_id' => 'order-id-123',
                    'status' => 'approved',
                    'status_detail' => 'İŞLEM BAŞARILI',
                    'error_code' => null,
                    'error_message' => null,
                ]
            ],
        ];
    }
}
