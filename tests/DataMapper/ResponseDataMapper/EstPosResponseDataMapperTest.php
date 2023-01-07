<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\EstPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EstPosResponseDataMapperTest extends TestCase
{
    /** @var EstPosResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt             = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestDataMapper = new EstPosRequestDataMapper($crypt);

        $this->responseDataMapper = new EstPosResponseDataMapper(
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
     * @dataProvider threeDPayPaymentDataProvider
     */
    public function testMap3DPayResponseData(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDHostPaymentDataProvider
     */
    public function testMap3DHostResponseData(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DHostResponseData($responseData);
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
     * @dataProvider historyTestDataProvider
     */
    public function testMapHistoryResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);
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
                        'order_id'         => '202210293885',
                        'group_id'         => '202210293885',
                        'trans_id'         => '22302V8rE11732',
                        'auth_code'        => 'P48911',
                        'ref_ret_num'      => '230200671758',
                        'proc_return_code' => '00',
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'error_code'       => null,
                        'error_message'    => null,
                        'recurring_id'     => null,
                        'extra'            => [
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
                ],
                //fail case
                [
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
                    'successData'  => [
                        'order_id'         => '20221029B541',
                        'group_id'         => '20221029B541',
                        'trans_id'         => '22302WcCC13836',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '99',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                        'error_code'       => 'CORE-2012',
                        'error_message'    => 'Kredi karti numarasi gecerli formatta degil.',
                        'recurring_id'     => null,
                        'extra'            => [
                            'SETTLEID'  => null,
                            'TRXDATE'   => '20221029 22:28:01',
                            'ERRORCODE' => 'CORE-2012',
                            'NUMCODE'   => '992012',
                        ],
                    ],
                ],
                //post fail case
                [
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
                    'successData'  => [
                        'order_id'         => '20221030FAC5',
                        'group_id'         => '20221030FAC5',
                        'trans_id'         => '22303Md4C19254',
                        'auth_code'        => 'P90325',
                        'ref_ret_num'      => '230300671782',
                        'proc_return_code' => '00',
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'error_code'       => null,
                        'error_message'    => null,
                        'recurring_id'     => null,
                        'extra'            => [
                            'SETTLEID'           => '2287',
                            'TRXDATE'            => '20221030 12:29:53',
                            'ERRORCODE'          => null,
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
                ],
            ];
    }


    public function threeDPaymentDataProvider(): array
    {
        return [
            [
                // 3D Auth fail case
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
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => null,
                    'tx_status'            => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704671.1667119085._nUCBN9o1Wh',
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '2022103076E7',
                ],
            ],
            [
                // 3D Success payment fail case
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
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => '05',
                    'tx_status'            => null,
                    'cavv'                 => 'ABABA##################AEJI=',
                    'md_error_message'     => null,
                    'group_id'             => '20221030FE4C',
                    'trans_id'             => '22303LtCH15933',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => '99',
                    'status'               => 'declined',
                    'status_detail'        => 'general_error',
                    'error_code'           => 'CORE-2603',
                    'error_message'        => 'Taksit tablosu icin gecersiz deger',
                    'recurring_id'         => null,
                    'extra'                => [
                        'SETTLEID'  => null,
                        'TRXDATE'   => '20221030 11:45:02',
                        'ERRORCODE' => 'CORE-2603',
                        'NUMCODE'   => '992603',
                    ],
                    'order_id'             => '20221030FE4C',
                ],
            ],
            [
                // Success case
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
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => '05',
                    'tx_status'            => null,
                    'cavv'                 => 'ABABCSQDGQAAAABllJMDdUQAEJI=',
                    'md_error_message'     => null,
                    'group_id'             => '202210304547',
                    'trans_id'             => '22303LzpJ16296',
                    'auth_code'            => '563339',
                    'ref_ret_num'          => '230311184777',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'recurring_id'         => null,
                    'extra'                => [
                        'SETTLEID'      => '2400',
                        'TRXDATE'       => '20221030 11:51:41',
                        'ERRORCODE'     => null,
                        'CARDBRAND'     => 'VISA',
                        'CARDISSUER'    => 'AKBANK T.A.S.',
                        'KAZANILANPUAN' => '000000010.00',
                        'NUMCODE'       => '00',
                    ],
                    'order_id'             => '202210304547',
                ],
            ],
        ];
    }


    public function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'  => [
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
                ],
                'expectedData' => [
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'eci'                  => '05',
                    'cavv'                 => 'ABABByBkEgAAAABllJMDdVWUGZE=',
                    'md_error_message'     => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704511.1667118159.BUW_iXHm4_6',
                    'order_id'             => '2022103030CB',
                    'trans_id'             => '22303LWsA14386',
                    'auth_code'            => 'P37891',
                    'ref_ret_num'          => '230300671764',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                ],
            ],
            'authFail1' => [
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
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'eci'                  => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214704541.1667118445.QQ1EjzXz8nm',
                    'order_id'             => '2022103008A3',
                    'proc_return_code'     => null,
                    'status'               => 'declined',
                ],
            ],
        ];
    }


    public function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1' => [
                // success case
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
                    'refreshtime'                     => '300',
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
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'eci'                  => '05',
                    'cavv'                 => 'ABABA##################UGZE=',
                    'md_error_message'     => null,
                    'order_id'             => '202210305DCF',
                    'status'               => 'approved',
                ],
            ],
            [
                //  3d fail case
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
                    'refreshtime'                     => '300',
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
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'proc_return_code'     => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'masked_number'        => '4355 08** **** 4358',
                    'month'                => '12',
                    'year'                 => '26',
                    'eci'                  => null,
                    'cavv'                 => null,
                    'md_error_message'     => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214705021.1667121056.gc2NvdPjGQ6',
                    'order_id'             => '20221030F11F',
                    'status'               => 'declined',
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
                        'order_id'         => '20221030FAC5',
                        'auth_code'        => 'P90325',
                        'proc_return_code' => '00',
                        'trans_id'         => '22303Md4C19254',
                        'error_message'    => 'Record(s) found for 20221030FAC5',
                        'ref_ret_num'      => '230300671782',
                        'order_status'     => "ORD_ID:20221030FAC5\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:\tTRANS_STAT:A\tAUTH_DTTM:2022-10-30 12:29:53.773\tCAPTURE_DTTM:\tAUTH_CODE:P90325\tTRANS_ID:22303Md4C19254",
                        'transaction_type'          => null,
                        'masked_number'    => '4355 08** **** 4358',
                        'num_code'         => '0',
                        'first_amount'     => 1.01,
                        'capture_amount'   => null,
                        'status'           => 'approved',
                        'error_code'       => null,
                        'status_detail'    => 'approved',
                        'capture'          => false,
                    ],
                ],
                'fail1'    => [
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
                        'order_id'         => null,
                        'auth_code'        => null,
                        'proc_return_code' => '99',
                        'trans_id'         => null,
                        'error_message'    => 'No record found for 2022103088D22',
                        'ref_ret_num'      => null,
                        'order_status'     => "ORD_ID:\tCHARGE_TYPE_CD:\tORIG_TRANS_AMT:\tCAPTURE_AMT:\tTRANS_STAT:\tAUTH_DTTM:\tCAPTURE_DTTM:\tAUTH_CODE:",
                        'transaction_type'          => null,
                        'masked_number'    => null,
                        'num_code'         => null,
                        'first_amount'     => null,
                        'capture_amount'   => null,
                        'status'           => 'declined',
                        'error_code'       => null,
                        'status_detail'    => 'general_error',
                        'capture'          => false,
                    ],
                ],
                //recurring success case
                [
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
                            0 => [
                                'order_id'         => '2022103097CD',
                                'order_status'     => "ORD_ID:2022103097CD\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:101\tTRANS_STAT:C\tAUTH_DTTM:2022-10-30 14:58:03.449\tCAPTURE_DTTM:2022-10-30 14:58:03.449\tAUTH_CODE:P34325\tTRANS_ID:22303O8EB19253",
                                'masked_number'    => '4355 08** **** 4358',
                                'status'           => 'C',
                                'auth_code'        => 'P34325',
                                'auth_time'        => '2022-10-30 14:58:03.449',
                                'proc_return_code' => '00',
                                'trans_id'         => '22303O8EB19253',
                                'ref_ret_num'      => '230300671790',
                                'first_amount'     => '101',
                                'capture_amount'   => '101',
                                'capture_time'     => '2022-10-30 14:58:03.449',
                                'capture'          => true,
                            ],
                            1 => [
                                'order_id'         => '2022103097CD-2',
                                'order_status'     => "ORD_ID:2022103097CD-2\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tTRANS_STAT:PN\tPLANNED_START_DTTM:2023-01-30 14:58:03.449",
                                'masked_number'    => '4355 08** **** 4358',
                                'status'           => 'PN',
                                'auth_code'        => null,
                                'auth_time'        => null,
                                'proc_return_code' => null,
                                'trans_id'         => null,
                                'ref_ret_num'      => null,
                                'first_amount'     => '101',
                                'capture_amount'   => null,
                                'capture_time'     => null,
                                'capture'          => false,
                            ],
                        ],
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
                        'trans_id'         => '22303MzZG10851',
                        'error_code'       => null,
                        'num_code'         => '00',
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail1'    => [
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
                        'trans_id'         => '22303M5IA11121',
                        'error_code'       => 'CORE-2008',
                        'num_code'         => '992008',
                        'error_message'    => 'İptal edilmeye uygun satış işlemi bulunamadı.',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                    ],
                ],
                [
                    // recurring success case
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

    public function refundTestDataProvider(): array
    {
        return
            [
                'fail1' => [
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
                        'group_id'         => '20221030B3FF',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '99',
                        'trans_id'         => '22303M8rC11328',
                        'num_code'         => '992508',
                        'error_code'       => 'CORE-2508',
                        'error_message'    => 'Iade yapilamaz, siparis gunsonuna girmemis.',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                    ],
                ],
            ];
    }

    public function historyTestDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'ErrMsg'         => '',
                        'ProcReturnCode' => '00',
                        'Response'       => 'Approved',
                        'OrderId'        => '20221030B3FF',
                        'Extra'          => [
                            'TERMINALID' => '00655020',
                            'MERCHANTID' => '655000200',
                            'NUMCODE'    => '0',
                            'TRX1'       => "C\tD\t101\t101\t2022-10-30 12:58:42.906\t2022-10-30 12:58:42.906\t\t\t\t99\t22303M8rC11328",
                            'TRX2'       => "C\tD\t101\t101\t2022-10-30 12:57:19.847\t2022-10-30 12:57:19.847\t\t\t\t99\t22303M7UA11280",
                            'TRX3'       => "S\tV\t101\t101\t2022-10-30 12:51:25.405\t2022-10-30 12:51:25.405\t2022-10-30 12:51:29.839\t230300671786\tP43467\t00\t22303MzZG10851",
                            'TRXCOUNT'   => '3',
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20221030B3FF',
                        'proc_return_code' => '00',
                        'error_message'    => null,
                        'num_code'         => '0',
                        'trans_count'      => '3',
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail1'    => [
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
                        'trans_count'      => '0',
                        'status'           => 'declined',
                        'status_detail'    => 'reject',
                    ],
                ],
            ];
    }
}
