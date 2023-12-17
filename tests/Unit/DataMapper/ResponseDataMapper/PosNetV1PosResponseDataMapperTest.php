<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\PosNetV1Pos;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper
 */
class PosNetV1PosResponseDataMapperTest extends TestCase
{
    private PosNetV1PosResponseDataMapper $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = CryptFactory::createGatewayCrypt(PosNetV1Pos::class, new NullLogger());
        $requestDataMapper        = new PosNetV1PosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);
        $this->responseDataMapper = new PosNetV1PosResponseDataMapper(
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

    /**
     * @dataProvider mapStatusResponseDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider mapCancelResponseDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider mapRefundResponseDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }


    public static function paymentTestDataProvider(): iterable
    {
        yield 'fail1' => [
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
                'order_id'         => null,
                'trans_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => '0127',
                'status'           => 'declined',
                'status_detail'    => null,
                'error_code'       => '0127',
                'error_message'    => 'ORDERID DAHA ONCE KULLANILMIS',
            ],
        ];
        yield 'success1' => [
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
                'order_id'         => null,
                'trans_id'         => null,
                'auth_code'        => '449324',
                'ref_ret_num'      => '159044932490000231',
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): \Generator
    {
        yield 'threeDAuthFail1' => [
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
                'transaction_security' => 'MPI fallback',
                'masked_number'        => '450634',
                'md_status'            => '0',
                'md_error_message'     => 'Not authenticated',
                'amount'               => 1.01,
                'trans_id'             => null,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => '0000000020230622A1C9',
                'proc_return_code'     => null,
                'status'               => 'declined',
            ],
        ];
        yield 'success1' => [
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
                'transaction_security' => 'Full 3D Secure',
                'masked_number'        => '540061',
                'md_status'            => '1',
                'md_error_message'     => null,
                'amount'               => 1.75,
                'trans_id'             => null,
                'auth_code'            => '449324',
                'ref_ret_num'          => '159044932490000231',
                'status_detail'        => 'approved',
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => 'ALA_0000080603153823',
                'proc_return_code'     => '00',
                'status'               => 'approved',
            ],
        ];

        yield 'threeDAuthFail2' => [
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
                'transaction_security' => 'MPI fallback',
                'masked_number'        => '540061',
                'md_status'            => '0',
                'md_error_message'     => 'Error',
                'amount'               => 1.75,
                'trans_id'             => null,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'order_id'             => 'ALA_0000080603153823',
                'proc_return_code'     => null,
                'status'               => 'declined',
            ],
        ];

        yield 'threeDAuthSuccessButPaymentFail' => [
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
                'transaction_security' => 'Full 3D Secure',
                'masked_number'        => '450634',
                'md_status'            => '1',
                'md_error_message'     => null,
                'amount'               => 1.01,
                'trans_id'             => null,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'status_detail'        => null,
                'error_code'           => '0148',
                'error_message'        => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                'order_id'             => '00000000202306226A90',
                'proc_return_code'     => '0148',
                'status'               => 'declined',
            ],
        ];
    }


    public static function mapStatusResponseDataProvider(): iterable
    {
        yield 'success1' => [
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
                'auth_code'        => null,
                'trans_id'         => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'date'             => null,
                'proc_return_code' => '0000',
                'status'           => 'approved',
                'status_detail'    => null,
                'error_code'       => null,
                'error_message'    => null,
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
                'auth_code'        => null,
                'trans_id'         => null,
                'ref_ret_num'      => null,
                'group_id'         => null,
                'date'             => null,
                'proc_return_code' => 'E219',
                'status'           => 'declined',
                'status_detail'    => null,
                'error_code'       => 'E219',
                'error_message'    => 'Kayıt Bulunamadı',
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
                'trans_id'         => null,
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
                'trans_id'         => null,
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
                'trans_id'         => null,
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
                'trans_id'         => null,
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
