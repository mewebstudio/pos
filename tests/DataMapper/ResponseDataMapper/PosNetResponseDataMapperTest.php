<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PosNet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PosNetResponseDataMapperTest extends TestCase
{
    /** @var PosNetResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = PosFactory::getGatewayCrypt(PosNet::class, new NullLogger());
        $requestDataMapper        = new PosNetRequestDataMapper($crypt);
        $this->responseDataMapper = new PosNetResponseDataMapper(
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
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        unset($actualData['all']);
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


    public function paymentTestDataProvider(): array
    {
        return
            [
                //success case
                [
                    'responseData' => [
                        'approved'   => '1',
                        'respCode'   => '',
                        'respText'   => '00',
                        'mac'        => 'DF2323A3BMC782QOP42RT',
                        'hostlogkey' => '0000000002P0806031',
                        'authCode'   => '901477',
                        'instInfo'   => [
                            'inst1' => '00',
                            'amnt1' => '000000000000',
                        ],
                        'pointInfo'  => [
                            'point'            => '00000228',
                            'pointAmount'      => '000000000114',
                            'totalPoint'       => '00000000',
                            'totalPointAmount' => '000000000000',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'trans_id'         => null,
                        'auth_code'        => '901477',
                        'ref_ret_num'      => '0000000002P0806031',
                        'proc_return_code' => '1',
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'error_code'       => null,
                        'error_message'    => '00',
                    ],
                ],
                //fail case
                [
                    'responseData' => [
                        'approved' => '0',
                        'respCode' => '0148',
                        'respText' => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'trans_id'         => null,
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '0',
                        'status'           => 'declined',
                        'status_detail'    => null,
                        'error_code'       => '0148',
                        'error_message'    => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                ],
                //fail case
                [
                    'responseData' => [
                        'approved'   => '2',
                        'respCode'   => '0127',
                        'respText'   => 'ORDERID DAHA ONCE KULLANILMIS 0127',
                        'hostlogkey' => '020527337090000191',
                        'authCode'   => '273370',
                        'tranDate'   => '190703093340',
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'trans_id'         => null,
                        'auth_code'        => '273370',
                        'ref_ret_num'      => '020527337090000191',
                        'proc_return_code' => '2',
                        'status'           => 'declined',
                        'status_detail'    => null,
                        'error_code'       => '0127',
                        'error_message'    => 'ORDERID DAHA ONCE KULLANILMIS 0127',
                    ],
                ],
            ];
    }


    public function threeDPaymentDataProvider(): array
    {
        return [
            'success1' => [
                'threeDResponseData' => [
                    'approved'                       => '1',
                    'respCode'                       => '',
                    'respText'                       => '',
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'installment'    => '00',
                        'point'          => '0',
                        'pointAmount'    => '0',
                        'txStatus'       => 'Y',
                        'mdStatus'       => '1',
                        'mdErrorMessage' => '',
                        'mac'            => 'y0fU6rRA0OvqJ5GN6uMdHVu6Xra7QR1qeT9rN7R1L+o=',
                    ],
                ],
                'paymentData'        => [
                    'approved'   => '1',
                    'respCode'   => '',
                    'respText'   => '00',
                    'mac'        => 'DF2323A3BMC782QOP42RT',
                    'hostlogkey' => '0000000002P0806031',
                    'authCode'   => '901477',
                    'instInfo'   => [
                        'inst1' => '00',
                        'amnt1' => '000000000000',
                    ],
                    'pointInfo'  => [
                        'point'            => '00000228',
                        'pointAmount'      => '000000000114',
                        'totalPoint'       => '00000000',
                        'totalPointAmount' => '000000000000',
                    ],
                ],
                'expectedData'       => [
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'md_error_message'     => null,
                    'trans_id'             => null,
                    'auth_code'            => '901477',
                    'ref_ret_num'          => '0000000002P0806031',
                    'error_code'           => null,
                    'error_message'        => '00',
                    'order_id'             => 'YKB_0000080603153823',
                    'proc_return_code'     => '1',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                ],
            ],
            'fail1'    => [
                // 3D Auth fail case
                'threeDResponseData' => [
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'installment'    => '00',
                        'point'          => '0',
                        'pointAmount'    => '0',
                        'txStatus'       => 'N',
                        'mdStatus'       => '9',
                        'mdErrorMessage' => 'None 3D - Secure Transaction',
                        'mac'            => 'ED7254A3ABC264QOP67MN',
                    ],
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '9',
                    'md_error_message'     => 'None 3D - Secure Transaction',
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => 'YKB_0000080603153823',
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                ],
            ],
        ];
    }


    public function statusTestDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'approved'     => '1',
                        'transactions' => [
                            'transaction' => [
                                'orderID'      => 'TDS_YKB_0000191010111730',
                                'ccno'         => '4506 34** **** 4637',
                                'amount'       => '1,16',
                                'currencyCode' => 'TL',
                                'authCode'     => '504289',
                                'tranDate'     => '2019-10-10 11:21:14.281',
                                'state'        => 'Sale',
                                'txnStatus'    => '1',
                                'hostlogkey'   => '021450428990000191',
                            ],
                        ],
                    ],
                    'expectedData' => [
                        'auth_code'        => '504289',
                        'trans_id'         => null,
                        'ref_ret_num'      => '021450428990000191',
                        'group_id'         => null,
                        'date'             => '2019-10-10 11:21:14.281',
                        'transaction_type' => 'pay',
                        'proc_return_code' => '1',
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'error_code'       => null,
                        'error_message'    => null,
                    ],
                ],
                'fail1'    => [
                    'responseData' => [
                        'approved' => '0',
                        'respCode' => '0148',
                        'respText' => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                    'expectedData' => [
                        'auth_code'        => null,
                        'trans_id'         => null,
                        'ref_ret_num'      => null,
                        'group_id'         => null,
                        'date'             => null,
                        'transaction_type' => null,
                        'proc_return_code' => '0',
                        'status'           => 'declined',
                        'status_detail'    => 'declined',
                        'error_code'       => '0148',
                        'error_message'    => 'INVALID MID TID IP. Hatal\u0131 IP:89.244.149.137',
                    ],
                ],
            ];
    }

    public function cancelTestDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'AuthCode'       => 'S05229',
                        'HostRefNum'     => '230423103898',
                        'ProcReturnCode' => '00',
                        'TransId'        => '20221031D388',
                        'ErrMsg'         => 'Onaylandı',
                        'CardHolderName' => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20221031D388',
                        'auth_code'        => 'S05229',
                        'ref_ret_num'      => '230423103898',
                        'proc_return_code' => '00',
                        'trans_id'         => null,
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail1'    => [
                    'responseData' => [
                        'AuthCode'       => '',
                        'HostRefNum'     => '230423103927',
                        'ProcReturnCode' => 'V013',
                        'TransId'        => '20221031D388',
                        'ErrMsg'         => 'Seçili İşlem Bulunamadı!',
                        'CardHolderName' => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20221031D388',
                        'auth_code'        => null,
                        'ref_ret_num'      => '230423103927',
                        'proc_return_code' => 'V013',
                        'trans_id'         => null,
                        'error_code'       => 'V013',
                        'error_message'    => 'Seçili İşlem Bulunamadı!',
                        'status'           => 'declined',
                        'status_detail'    => 'reject',
                    ],
                ],
            ];
    }

    public function refundTestDataProvider(): array
    {
        return
            [
                'fail1' => [
                    'responseData' => [
                        'approved' => '0',
                        'respCode' => '0148',
                        'respText' => 'INVALID MID TID IP. Hatalı IP:89.244.149.137',
                    ],
                    'expectedData' => [
                        'auth_code'        => null,
                        'trans_id'         => null,
                        'ref_ret_num'      => null,
                        'group_id'         => null,
                        'date'             => null,
                        'transaction_type' => null,
                        'proc_return_code' => '0',
                        'status'           => 'declined',
                        'status_detail'    => 'declined',
                        'error_code'       => '0148',
                        'error_message'    => 'INVALID MID TID IP. Hatalı IP:89.244.149.137',
                    ],
                ],
            ];
    }
}
