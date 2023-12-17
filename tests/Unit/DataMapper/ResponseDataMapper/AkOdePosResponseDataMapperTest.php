<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\AkOdePosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\AkOdePosResponseDataMapper;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\AkOdePos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AkOdePosResponseDataMapper
 */
class AkOdePosResponseDataMapperTest extends TestCase
{
    private AkOdePosResponseDataMapper $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt             = CryptFactory::createGatewayCrypt(AkOdePos::class, new NullLogger());
        $requestDataMapper = new AkOdePosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);

        $this->responseDataMapper = new AkOdePosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
    }

    /**
     * @dataProvider paymentDataProvider
     */
    public function testMapPaymentResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData);
        unset($actualData['all']);
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
     * @dataProvider statusResponseDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider historyDataProvider
     */
    public function testMapHistoryResponse(array $responseData, array $expectedData)
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);
        unset($actualData['all']);
        $this->assertSame($expectedData, $actualData);
    }

    public static function paymentDataProvider(): iterable
    {
        yield 'success1' => [
            'responseData' => [
                'OrderId'             => '202312053421',
                'BankResponseCode'    => '00',
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => '2000000000032562',
                'CardHolderName'      => null,
                'Code'                => 0,
                'Message'             => 'Başarılı',
            ],
            'expectedData' => [
                'auth_code'        => null,
                'transaction_type' => null,
                'order_id'         => '202312053421',
                'trans_id'         => '2000000000032562',
                'ref_ret_num'      => null,
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
            ],
        ];
        yield 'success_post_pay' => [
            'responseData' => [
                'OrderId'             => '202312053F93',
                'BankResponseCode'    => '00',
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => '2000000000032560',
                'Code'                => 0,
                'Message'             => 'Başarılı',
            ],
            'expectedData' => [
                'auth_code'        => null,
                'transaction_type' => null,
                'order_id'         => '202312053F93',
                'trans_id'         => '2000000000032560',
                'ref_ret_num'      => null,
                'proc_return_code' => '00',
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'error_code'       => null,
                'error_message'    => null,
            ],
        ];
        yield 'error_post_pay' => [
            'responseData' => [
                'OrderId'             => '202312053F93',
                'BankResponseCode'    => null,
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => null,
                'Code'                => 101,
                'Message'             => 'Orjinal Kayıt Bulunamadı',
            ],
            'expectedData' => [
                'auth_code'        => null,
                'transaction_type' => null,
                'order_id'         => '202312053F93',
                'trans_id'         => null,
                'ref_ret_num'      => null,
                'proc_return_code' => null,
                'status'           => 'declined',
                'status_detail'    => 'transaction_not_found',
                'error_code'       => null,
                'error_message'    => 'Orjinal Kayıt Bulunamadı',
            ],
        ];
        yield 'error_hash_error' => [
            'responseData' => [
                'OrderId'             => null,
                'BankResponseCode'    => null,
                'BankResponseMessage' => null,
                'AuthCode'            => null,
                'HostReferenceNumber' => null,
                'TransactionId'       => null,
                'CardHolderName'      => null,
                'Code'                => 997,
                'Message'             => 'Hash Hatası',
            ],
            'expectedData' => [
                'auth_code'        => null,
                'transaction_type' => null,
                'order_id'         => null,
                'trans_id'         => null,
                'ref_ret_num'      => null,
                'proc_return_code' => null,
                'status'           => 'declined',
                'status_detail'    => null,
                'error_code'       => null,
                'error_message'    => 'Hash Hatası',
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
                    'currency'             => PosInterface::CURRENCY_TRY,
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
                    'currency'             => PosInterface::CURRENCY_TRY,
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
                    'currency'             => PosInterface::CURRENCY_TRY,
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


    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1' => [
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '202312034E91',
                    'MdStatus'            => '1',
                    'ThreeDSessionId'     => 'P40D18956D9C94188ABF6C87B37075AF7B1029577C4BF4BADB8E86058919000F4',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '1',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
                ],
                'expectedData' => [
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_type'     => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'tx_status'            => 'PAYMENT_COMPLETED',
                    'md_error_message'     => null,
                    'order_id'             => '202312034E91',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                ],
            ],
            'auth_fail'    => [
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '20231203E148',
                    'MdStatus'            => '0',
                    'ThreeDSessionId'     => 'P2462E945F4554146B8E4A4306B7FF6C16D4047086D304B61B53430BD7CD02F51',
                    'BankResponseCode'    => 'MD:0',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '0',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'C7Vbcr3adDhlWEr9vT9oFHikjrjEiv5DSBORu0YnOATkF/YirOziwouAGk8vqB29oeyPBnlFgBih7bLN9YWweQ==',
                ],
                'expectedData' => [
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'status_detail'        => null,
                    'transaction_type'     => null,
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'tx_status'            => 'ERROR',
                    'md_error_message'     => null,
                    'order_id'             => '20231203E148',
                    'proc_return_code'     => 'MD:0',
                    'status'               => 'declined',
                    'error_code'           => 'MD:0',
                    'error_message'        => null,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1' => [
                'paymentData'  => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '20231203626F',
                    'MdStatus'            => '1',
                    'ThreeDSessionId'     => 'P8A6DB3F7FDB74A3F903C44883401F178609178BC431C47DE92E4811587C65589',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '1',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'A+bjgxp/uIQjpsY+cEpUJcu+m6xXMgpDz7DOjtQ8TgKgJaFFsLGKkpNKOYzInqfkJ6U9+S8mxGFBv4o4WqC4hg==',
                ],
                'expectedData' => [
                    'trans_id'             => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'status_detail'        => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_type'     => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'tx_status'            => 'PAYMENT_COMPLETED',
                    'md_error_message'     => null,
                    'order_id'             => '20231203626F',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                ],
            ],
        ];
    }


    public static function statusResponseDataProvider(): array
    {
        return
            [
                'success1'                         => [
                    'responseData' => [
                        'TransactionType'          => 2,
                        'CreateDate'               => '20231205224003',
                        'OrderId'                  => '20231205D497',
                        'BankResponseCode'         => '00',
                        'BankResponseMessage'      => null,
                        'AuthCode'                 => null,
                        'HostReferenceNumber'      => null,
                        'Amount'                   => 101,
                        'Currency'                 => 949,
                        'InstallmentCount'         => 0,
                        'ClientId'                 => 1000000494,
                        'CardNo'                   => '41595600****7732',
                        'RequestStatus'            => 2,
                        'RefundedAmount'           => 0,
                        'PostAuthedAmount'         => 0,
                        'TransactionId'            => 0,
                        'CommissionStatus'         => null,
                        'NetAmount'                => 101,
                        'MerchantCommissionAmount' => 0,
                        'MerchantCommissionRate'   => null,
                        'CardBankId'               => 0,
                        'CardTypeId'               => 0,
                        'ValorDate'                => 0,
                        'TransactionDate'          => 0,
                        'BankValorDate'            => 0,
                        'ExtraParameters'          => null,
                        'Code'                     => 0,
                        'Message'                  => null,
                    ],
                    'expectedData' => [
                        'order_id'         => '20231205D497',
                        'auth_code'        => null,
                        'proc_return_code' => '00',
                        'trans_id'         => null,
                        'trans_date'       => null,
                        'error_message'    => null,
                        'ref_ret_num'      => null,
                        'masked_number'    => '41595600****7732',
                        'order_status'     => 'CANCELED',
                        'transaction_type' => 'pre',
                        'capture_amount'   => 1.01,
                        'status'           => 'approved',
                        'error_code'       => null,
                        'status_detail'    => 'approved',
                        'capture'          => true,
                        'currency'         => 'TRY',
                        'first_amount'     => 1.01,
                    ],
                ],
                'success2'                         => [
                    'responseData' => [
                        'TransactionType'          => 1,
                        'CreateDate'               => '20231204002334',
                        'OrderId'                  => '20231203CA6D',
                        'BankResponseCode'         => null,
                        'BankResponseMessage'      => null,
                        'AuthCode'                 => null,
                        'HostReferenceNumber'      => null,
                        'Amount'                   => 101,
                        'Currency'                 => 949,
                        'InstallmentCount'         => 0,
                        'ClientId'                 => 1000000494,
                        'CardNo'                   => null,
                        'RequestStatus'            => 10,
                        'RefundedAmount'           => 0,
                        'PostAuthedAmount'         => 0,
                        'TransactionId'            => 0,
                        'CommissionStatus'         => null,
                        'NetAmount'                => 101,
                        'MerchantCommissionAmount' => 0,
                        'MerchantCommissionRate'   => null,
                        'CardBankId'               => 0,
                        'CardTypeId'               => 0,
                        'ValorDate'                => 0,
                        'TransactionDate'          => 0,
                        'BankValorDate'            => 0,
                        'ExtraParameters'          => null,
                        'Code'                     => 0,
                        'Message'                  => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20231203CA6D',
                        'auth_code'        => null,
                        'proc_return_code' => null,
                        'trans_id'         => null,
                        'trans_date'       => null,
                        'error_message'    => null,
                        'ref_ret_num'      => null,
                        'masked_number'    => null,
                        'order_status'     => 10,
                        'transaction_type' => 'pay',
                        'capture_amount'   => 1.01,
                        'status'           => 'approved',
                        'error_code'       => null,
                        'status_detail'    => 'approved',
                        'capture'          => true,
                        'currency'         => 'TRY',
                        'first_amount'     => 1.01,
                    ],
                ],
                'success_pre_auth_completed_order' => [
                    'responseData' => [
                        'TransactionType'          => 2,
                        'CreateDate'               => '20231210132528',
                        'OrderId'                  => '20231210A7D0',
                        'BankResponseCode'         => '00',
                        'BankResponseMessage'      => null,
                        'AuthCode'                 => null,
                        'HostReferenceNumber'      => null,
                        'Amount'                   => 101,
                        'Currency'                 => 949,
                        'InstallmentCount'         => 2,
                        'ClientId'                 => 1000000494,
                        'CardNo'                   => '41595600****7732',
                        'RequestStatus'            => 5,
                        'RefundedAmount'           => 0,
                        'PostAuthedAmount'         => 101,
                        'TransactionId'            => 0,
                        'CommissionStatus'         => null,
                        'NetAmount'                => 101,
                        'MerchantCommissionAmount' => 0,
                        'MerchantCommissionRate'   => null,
                        'CardBankId'               => 0,
                        'CardTypeId'               => 0,
                        'ValorDate'                => 0,
                        'TransactionDate'          => 0,
                        'BankValorDate'            => 0,
                        'ExtraParameters'          => null,
                        'Code'                     => 0,
                        'Message'                  => null,
                    ],
                    'expectedData' => [
                        'order_id'         => '20231210A7D0',
                        'auth_code'        => null,
                        'proc_return_code' => '00',
                        'trans_id'         => null,
                        'trans_date'       => null,
                        'error_message'    => null,
                        'ref_ret_num'      => null,
                        'masked_number'    => '41595600****7732',
                        'order_status'     => 'PRE_AUTH_COMPLETED',
                        'transaction_type' => 'pre',
                        'capture_amount'   => 1.01,
                        'status'           => 'approved',
                        'error_code'       => null,
                        'status_detail'    => 'approved',
                        'capture'          => true,
                        'currency'         => 'TRY',
                        'first_amount'     => 1.01,
                    ],
                ],
                'fail1'                            => [
                    'responseData' => [
                        'TransactionType'          => 0,
                        'CreateDate'               => null,
                        'OrderId'                  => null,
                        'BankResponseCode'         => null,
                        'BankResponseMessage'      => null,
                        'AuthCode'                 => null,
                        'HostReferenceNumber'      => null,
                        'Amount'                   => 0,
                        'Currency'                 => 0,
                        'InstallmentCount'         => 0,
                        'ClientId'                 => 0,
                        'CardNo'                   => null,
                        'RequestStatus'            => 0,
                        'RefundedAmount'           => 0,
                        'PostAuthedAmount'         => 0,
                        'TransactionId'            => 0,
                        'CommissionStatus'         => null,
                        'NetAmount'                => 0,
                        'MerchantCommissionAmount' => 0,
                        'MerchantCommissionRate'   => null,
                        'CardBankId'               => 0,
                        'CardTypeId'               => 0,
                        'ValorDate'                => null,
                        'TransactionDate'          => 0,
                        'BankValorDate'            => 0,
                        'ExtraParameters'          => null,
                        'Code'                     => 0,
                        'Message'                  => '',
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'auth_code'        => null,
                        'proc_return_code' => null,
                        'trans_id'         => null,
                        'trans_date'       => null,
                        'error_message'    => null,
                        'ref_ret_num'      => null,
                        'masked_number'    => null,
                        'order_status'     => 'ERROR',
                        'transaction_type' => null,
                        'capture_amount'   => 0.0,
                        'status'           => 'approved',
                        'error_code'       => null,
                        'status_detail'    => 'approved',
                        'capture'          => true,
                        'currency'         => '0',
                        'first_amount'     => 0.0,
                    ],
                ],
            ];
    }

    public static function cancelDataProvider(): array
    {
        return
            [
                'success1' => [
                    'responseData' => [
                        'OrderId'             => '202312058278',
                        'BankResponseCode'    => '00',
                        'BankResponseMessage' => null,
                        'AuthCode'            => null,
                        'HostReferenceNumber' => null,
                        'TransactionId'       => '2000000000032548',
                        'Code'                => 0,
                        'Message'             => 'Başarılı',
                    ],
                    'expectedData' => [
                        'order_id'         => '202312058278',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => '00',
                        'trans_id'         => '2000000000032548',
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                    ],
                ],
                'fail_1'   => [
                    'responseData' => [
                        'OrderId'             => '20231203CA6D',
                        'BankResponseCode'    => null,
                        'BankResponseMessage' => null,
                        'AuthCode'            => null,
                        'HostReferenceNumber' => null,
                        'TransactionId'       => null,
                        'Code'                => 101,
                        'Message'             => 'Orjinal Kayıt Bulunamadı',
                    ],
                    'expectedData' => [
                        'order_id'         => '20231203CA6D',
                        'auth_code'        => null,
                        'ref_ret_num'      => null,
                        'proc_return_code' => null,
                        'trans_id'         => null,
                        'error_code'       => 101,
                        'error_message'    => 'Orjinal Kayıt Bulunamadı',
                        'status'           => 'declined',
                        'status_detail'    => 'transaction_not_found',
                    ],
                ],
            ];
    }

    public static function refundDataProvider(): array
    {
        return [
            'fail1'    => [
                'responseData' => [
                    'OrderId'             => null,
                    'BankResponseCode'    => null,
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => null,
                    'Code'                => 999,
                    'Message'             => 'Genel Hata',
                ],
                'expectedData' => [
                    'order_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => null,
                    'trans_id'         => null,
                    'error_code'       => 999,
                    'error_message'    => 'Genel Hata',
                    'status'           => 'declined',
                    'status_detail'    => 'general_error',
                ],
            ],
            'fail2'    => [
                'responseData' => [
                    'OrderId'             => '202312051B4E',
                    'BankResponseCode'    => null,
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => null,
                    'Code'                => 101,
                    'Message'             => 'Orjinal Kayıt Bulunamadı',
                ],
                'expectedData' => [
                    'order_id'         => '202312051B4E',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => null,
                    'trans_id'         => null,
                    'error_code'       => 101,
                    'error_message'    => 'Orjinal Kayıt Bulunamadı',
                    'status'           => 'declined',
                    'status_detail'    => 'transaction_not_found',
                ],
            ],
            'success1' => [
                'responseData' => [
                    'OrderId'             => '202312051B4E',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => null,
                    'AuthCode'            => null,
                    'HostReferenceNumber' => null,
                    'TransactionId'       => '2000000000032550',
                    'Code'                => 0,
                    'Message'             => 'Başarılı',
                ],
                'expectedData' => [
                    'order_id'         => '202312051B4E',
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => '00',
                    'trans_id'         => '2000000000032550',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                ],
            ],
        ];
    }

    public static function historyDataProvider(): array
    {
        return
            [
                'fail_validation'                  => [
                    'responseData' => [
                        'Code'             => 998,
                        'message'          => 'Validasyon Hatası',
                        'ValidationErrors' => [
                            "Could not convert string to integer: 20231209123936. Path 'transactionDate', line 1, position 113.'",
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'proc_return_code' => null,
                        'error_code'       => 998,
                        'error_message'    => 'Validasyon Hatası',
                        'status'           => 'declined',
                        'status_detail'    => 'invalid_transaction',
                        'transactions'     => [],
                    ],
                ],
                'fail_when_no_hash_value_sent'     => [
                    'responseData' => [
                        'Count'        => 0,
                        'Transactions' => null,
                        'Code'         => 999,
                        'Message'      => 'Genel Hata',
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'proc_return_code' => null,
                        'error_code'       => 999,
                        'error_message'    => 'Genel Hata',
                        'status'           => 'declined',
                        'status_detail'    => 'general_error',
                        'transactions'     => [],
                    ],
                ],
                'success_no_order_found'           => [
                    'responseData' => [
                        'Count'        => 0,
                        'Transactions' => [],
                        'Code'         => 0,
                        'Message'      => 'Başarılı',
                    ],
                    'expectedData' => [
                        'order_id'         => null,
                        'proc_return_code' => null,
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'transactions'     => [],
                    ],
                ],
                'success_only_payment_transaction' => [
                    'responseData' => [
                        'Count'        => 1,
                        'Code'         => 0,
                        'Message'      => 'Başarılı',
                        'Transactions' => [
                            [
                                'TransactionType'          => 1,
                                'CreateDate'               => '20231209154531',
                                'OrderId'                  => '20231209C3AE',
                                'BankResponseCode'         => '00',
                                'BankResponseMessage'      => null,
                                'AuthCode'                 => null,
                                'HostReferenceNumber'      => null,
                                'Amount'                   => 101,
                                'Currency'                 => 949,
                                'InstallmentCount'         => 0,
                                'ClientId'                 => 1000000494,
                                'CardNo'                   => '41595600****7732',
                                'RequestStatus'            => 1,
                                'RefundedAmount'           => 0,
                                'PostAuthedAmount'         => 0,
                                'TransactionId'            => 2000000000032596,
                                'CommissionStatus'         => null,
                                'NetAmount'                => 101,
                                'MerchantCommissionAmount' => 0,
                                'MerchantCommissionRate'   => null,
                                'CardBankId'               => 13,
                                'CardTypeId'               => 1,
                                'ValorDate'                => 0,
                                'TransactionDate'          => 20231209,
                                'BankValorDate'            => 0,
                                'ExtraParameters'          => null,
                                'Code'                     => 0,
                                'Message'                  => 'Success',
                            ],
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20231209C3AE',
                        'proc_return_code' => null,
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'transactions'     => [
                            [
                                'order_id'         => '20231209C3AE',
                                'auth_code'        => null,
                                'proc_return_code' => '00',
                                'trans_id'         => 2000000000032596,
                                'trans_date'       => 20231209,
                                'error_message'    => null,
                                'ref_ret_num'      => null,
                                'masked_number'    => '41595600****7732',
                                'order_status'     => 'PAYMENT_COMPLETED',
                                'transaction_type' => 'pay',
                                'capture_amount'   => 1.01,
                                'status'           => 'approved',
                                'error_code'       => null,
                                'status_detail'    => 'approved',
                                'capture'          => true,
                                'currency'         => 'TRY',
                                'first_amount'     => 1.01,
                            ],
                        ],
                    ],
                ],
                'success_multiple_transactions'    => [
                    'responseData' => [
                        'Count'        => 2,
                        'Code'         => 0,
                        'Message'      => 'Başarılı',
                        'Transactions' => [
                            [
                                'TransactionType'          => 1,
                                'CreateDate'               => '20231209154531',
                                'OrderId'                  => '20231209C3AE',
                                'BankResponseCode'         => '00',
                                'BankResponseMessage'      => null,
                                'AuthCode'                 => null,
                                'HostReferenceNumber'      => null,
                                'Amount'                   => 101,
                                'Currency'                 => 949,
                                'InstallmentCount'         => 0,
                                'ClientId'                 => 1000000494,
                                'CardNo'                   => '41595600****7732',
                                'RequestStatus'            => 2,
                                'RefundedAmount'           => 0,
                                'PostAuthedAmount'         => 0,
                                'TransactionId'            => 2000000000032596,
                                'CommissionStatus'         => null,
                                'NetAmount'                => 101,
                                'MerchantCommissionAmount' => 0,
                                'MerchantCommissionRate'   => null,
                                'CardBankId'               => 13,
                                'CardTypeId'               => 1,
                                'ValorDate'                => 0,
                                'TransactionDate'          => 20231209,
                                'BankValorDate'            => 0,
                                'ExtraParameters'          => null,
                                'Code'                     => 0,
                                'Message'                  => 'Success',
                            ],
                            [
                                'TransactionType'          => 4,
                                'CreateDate'               => '20231209154644',
                                'OrderId'                  => '20231209C3AE',
                                'BankResponseCode'         => '00',
                                'BankResponseMessage'      => null,
                                'AuthCode'                 => null,
                                'HostReferenceNumber'      => null,
                                'Amount'                   => 101,
                                'Currency'                 => 949,
                                'InstallmentCount'         => 0,
                                'ClientId'                 => 1000000494,
                                'CardNo'                   => '41595600****7732',
                                'RequestStatus'            => 1,
                                'RefundedAmount'           => 0,
                                'PostAuthedAmount'         => 0,
                                'TransactionId'            => 2000000000032597,
                                'CommissionStatus'         => null,
                                'NetAmount'                => 101,
                                'MerchantCommissionAmount' => 0,
                                'MerchantCommissionRate'   => null,
                                'CardBankId'               => 13,
                                'CardTypeId'               => 1,
                                'ValorDate'                => 0,
                                'TransactionDate'          => 20231209,
                                'BankValorDate'            => 0,
                                'ExtraParameters'          => null,
                                'Code'                     => 0,
                                'Message'                  => 'Başarılı',
                            ],
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20231209C3AE',
                        'proc_return_code' => null,
                        'error_code'       => null,
                        'error_message'    => null,
                        'status'           => 'approved',
                        'status_detail'    => 'approved',
                        'transactions'     => [
                            [
                                'order_id'         => '20231209C3AE',
                                'auth_code'        => null,
                                'proc_return_code' => '00',
                                'trans_id'         => 2000000000032596,
                                'trans_date'       => 20231209,
                                'error_message'    => null,
                                'ref_ret_num'      => null,
                                'masked_number'    => '41595600****7732',
                                'order_status'     => 'CANCELED',
                                'transaction_type' => 'pay',
                                'capture_amount'   => 1.01,
                                'status'           => 'approved',
                                'error_code'       => null,
                                'status_detail'    => 'approved',
                                'capture'          => true,
                                'currency'         => 'TRY',
                                'first_amount'     => 1.01,
                            ],
                            [
                                'order_id'         => '20231209C3AE',
                                'auth_code'        => null,
                                'proc_return_code' => '00',
                                'trans_id'         => 2000000000032597,
                                'trans_date'       => 20231209,
                                'error_message'    => null,
                                'ref_ret_num'      => null,
                                'masked_number'    => '41595600****7732',
                                'order_status'     => 'PAYMENT_COMPLETED',
                                'transaction_type' => 'cancel',
                                'capture_amount'   => 1.01,
                                'status'           => 'approved',
                                'error_code'       => null,
                                'status_detail'    => 'approved',
                                'capture'          => true,
                                'currency'         => 'TRY',
                                'first_amount'     => 1.01,
                            ],
                        ],
                    ],
                ],
            ];
    }
}
