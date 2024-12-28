<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class GarantiPosResponseDataMapperTest extends TestCase
{
    private GarantiPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new GarantiPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new GarantiPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["2", true]
     * ["3", true]
     * ["4", true]
     * ["7", false]
     * ["1", true]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"mdstatus": "1"}, "1"]
     *
     */
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $actual = $this->responseDataMapper->extractMdStatus($responseData);
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider paymentTestDataProvider
     */
    public function testMapPaymentResponse(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, $order);
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
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
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
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

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DHostResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DHostResponseData([], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    /**
     * @dataProvider threeDPayPaymentDataProvider
     */
    public function testMap3DPayResponseData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($responseData, $txType, $order);
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
     * @dataProvider orderHistoryTestDataProvider
     */
    public function testOrderMapHistoryResponse(array $responseData, array $expectedData): void
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

        foreach (array_keys($actualData['transactions']) as $key) {
            $this->assertEquals($expectedData['transactions'][$key]['transaction_time'], $actualData['transactions'][$key]['transaction_time']);
            $this->assertEquals($expectedData['transactions'][$key]['capture_time'], $actualData['transactions'][$key]['capture_time']);
            unset($actualData['transactions'][$key]['transaction_time'], $expectedData['transactions'][$key]['transaction_time']);
            unset($actualData['transactions'][$key]['capture_time'], $expectedData['transactions'][$key]['capture_time']);
            \ksort($actualData['transactions'][$key]);
            \ksort($expectedData['transactions'][$key]);
        }

        $this->assertCount($actualData['trans_count'], $actualData['transactions']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

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
                $actualData['transactions'][1]['transaction_time']
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
        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
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

    public static function paymentTestDataProvider(): array
    {
        return [
            'success1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID' => '20221101D723',
                        'GroupID' => '',
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'HOST',
                            'Code'       => '00',
                            'ReasonCode' => '00',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '230508300434',
                        'AuthCode'         => '304919',
                        'BatchNum'         => '004951',
                        'SequenceNum'      => '000015',
                        'ProvDate'         => '20221101 13:14:19',
                        'CardNumberMasked' => '428220******8015',
                        'CardHolderName'   => 'HA*** YIL***',
                        'CardType'         => 'FLEXI',
                        'HashData'         => '1AAF91AE8000A94BF0B3FF42222E75E5837C98B9',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData' => [
                    'transaction_id'    => null,
                    'transaction_type'  => 'pay',
                    'payment_model'     => 'regular',
                    'group_id'          => null,
                    'order_id'          => '20221101D723',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'auth_code'         => '304919',
                    'ref_ret_num'       => '230508300434',
                    'batch_num'         => '004951',
                    'proc_return_code'  => '00',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'error_code'        => null,
                    'error_message'     => null,
                    'installment_count' => null,
                    'transaction_time'  => new \DateTimeImmutable('2022-11-01 13:14:19'),
                ],
            ],
            'fail_1'   => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID' => '2022110189E1',
                        'GroupID' => '',
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '92',
                            'ReasonCode' => '0002',
                            'Message'    => 'Declined',
                            'ErrorMsg'   => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                            'SysErrMsg'  => 'TxnAmount field must not be zero DOUBLE value because of the Mandatory Rule:zero',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20221101 13:19:22',
                        'CardNumberMasked' => '428220******8015',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => 'FCA7BDA4204E448FF2695358D22E3B75125DC396',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData' => [
                    'transaction_id'    => null,
                    'transaction_type'  => 'pay',
                    'payment_model'     => 'regular',
                    'group_id'          => null,
                    'order_id'          => '2022110189E1',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'auth_code'         => null,
                    'ref_ret_num'       => null,
                    'batch_num'         => null,
                    'proc_return_code'  => '92',
                    'status'            => 'declined',
                    'status_detail'     => 'invalid_transaction',
                    'error_code'        => '0002',
                    'error_message'     => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                    'installment_count' => null,
                    'transaction_time'  => null,
                ],
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'paymentFail1'               => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'xid'                   => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Authenticated',
                    'txnstatus'             => 'Y',
                    'eci'                   => '02',
                    'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'paressyntaxok'         => 'true',
                    'paresverified'         => 'true',
                    'version'               => '2.0',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'vendorcode'            => '',
                    'cavvalgorithm'         => '3',
                    'md'                    => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==',
                    'terminalid'            => '30691298',
                    'oid'                   => '20221101295D',
                    'authcode'              => '',
                    'response'              => '',
                    'errmsg'                => '',
                    'hostmsg'               => '',
                    'procreturncode'        => '',
                    'transid'               => '20221101295D',
                    'hostrefnum'            => '',
                    'rnd'                   => 'Nvx8y+0R3sR5mfDVLtVD',
                    'hash'                  => 'K1eaT12s4oPbvQDfA6YIMCfH6HQ=',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '3069129820221101295D1jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==Nvx8y+0R3sR5mfDVLtVD',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '428220***8015',
                    'apiversion'            => 'v0.01',
                    'orderid'               => '20221101295D',
                    'txninstallmentcount'   => '',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => 'DCC371FD21BCFDEE9F9B4B86D3CD304C34D3FD51',
                    'secure3dsecuritylevel' => '3D',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '0',
                    'successurl'            => 'http://localhost/garanti/3d/response.php',
                    'customeripaddress'     => '172.26.0.1',
                    'txntype'               => 'sales',
                ],
                'paymentData'        => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID' => '20221101295D',
                        'GroupID' => '',
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '92',
                            'ReasonCode' => '0002',
                            'Message'    => 'Declined',
                            'ErrorMsg'   => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                            'SysErrMsg'  => 'TxnAmount field must not be zero DOUBLE value because of the Mandatory Rule:zero',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20221101 14:02:40',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => '520A24F019779AEA141ECA8C2F2B3654C65286FE',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData'       => [
                    'order_id'             => '20221101295D',
                    'transaction_id'       => '20221101295D',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'transaction_security' => 'Full 3D Secure',
                    'proc_return_code'     => '92',
                    'md_status'            => '1',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'masked_number'        => '428220***8015',
                    'amount'               => 0.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Y',
                    'eci'                  => '02',
                    'cavv'                 => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'error_code'           => '0002',
                    'error_message'        => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                    'md_error_message'     => null,
                    'group_id'             => null,
                    'batch_num'            => null,
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                    'transaction_time'     => null,
                ],
            ],
            'paymentFail_wrong_cvc_code' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'xid'                   => 'fbd8e1ec-3d98-499d-9578-cf5380f208bc',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Y-status/Challenge authentication via ACS: https://gbemv3dsecure.garanti.com.tr/web/creq',
                    'txnstatus'             => null,
                    'eci'                   => '02',
                    'cavv'                  => 'xgT+4XVHAAAAAAAAAAAAAAAAAAA=',
                    'paressyntaxok'         => null,
                    'paresverified'         => null,
                    'version'               => null,
                    'ireqcode'              => null,
                    'ireqdetail'            => null,
                    'vendorcode'            => null,
                    'cavvalgorithm'         => null,
                    'md'                    => 'aW5kZXg6MDJrx8O9qwUvrCPAHSeJG+tDd41i3MI4NE2sFbvci41eCZnWHTzhbenpZpxHwicr3CWCseFLj49EJGq31hSU1Ll+j4PQ3y2dm+BzWtOIhoc7eqN7mtmCUt1bnoOk1bHvo49vm44jgIjzXcXY7kLFj+VdhG71kIx40nXmFstuuNn3kQ==',
                    'terminalid'            => '30691298',
                    'oid'                   => '20231223D98E',
                    'authcode'              => null,
                    'response'              => null,
                    'errmsg'                => null,
                    'hostmsg'               => null,
                    'procreturncode'        => null,
                    'transid'               => '20231223D98E',
                    'hostrefnum'            => null,
                    'rnd'                   => '/SXt7jTwxd7XjieE1z9H',
                    'hash'                  => 'C8B7F490BBC076A280B8FFBF33608D3CF73E4E6272699C3A57D9BA4B16905EEE9BE6FC41FCE0401FF66EB2E74441EC12A12BCC00F861F922FE7126307D42F456',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '3069129820231223D98E1xgT+4XVHAAAAAAAAAAAAAAAAAAA=02aW5kZXg6MDJrx8O9qwUvrCPAHSeJG+tDd41i3MI4NE2sFbvci41eCZnWHTzhbenpZpxHwicr3CWCseFLj49EJGq31hSU1Ll+j4PQ3y2dm+BzWtOIhoc7eqN7mtmCUt1bnoOk1bHvo49vm44jgIjzXcXY7kLFj+VdhG71kIx40nXmFstuuNn3kQ==/SXt7jTwxd7XjieE1z9H',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '55496087****1500',
                    'apiversion'            => '512',
                    'orderid'               => '20231223D98E',
                    'txninstallmentcount'   => null,
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '4D82C430D5C860D7B78D180DFA7F03C0C75ED796E97A9486762B6F09F66F18399111E3501CD56D560D01CF3D96399B637BE6A8531190144264585AEAB372483F',
                    'secure3dsecuritylevel' => '3D',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '101',
                    'successurl'            => 'http://localhost/garanti/3d/response.php',
                    'txntype'               => 'sales',
                    'customeripaddress'     => '172.26.0.1',
                ],
                'paymentData'        => [
                    'Mode'        => null,
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '172.26.0.1',
                        'EmailAddress' => null,
                    ],
                    'Order'       => [
                        'OrderID' => '20231223D98E',
                        'GroupID' => null,
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'HOST',
                            'Code'       => '12',
                            'ReasonCode' => '12',
                            'Message'    => 'Declined',
                            'ErrorMsg'   => 'İşleminizi gerçekleştiremiyoruz.Tekrar deneyiniz',
                            'SysErrMsg'  => 'CVC2/4CSC HATALI',
                        ],
                        'RetrefNum'        => '335709663083',
                        'AuthCode'         => null,
                        'BatchNum'         => '005546',
                        'SequenceNum'      => '000082',
                        'ProvDate'         => '20231223 19:28:20',
                        'CardNumberMasked' => '55496087****1500',
                        'CardHolderName'   => '4517******* 4517**********',
                        'CardType'         => 'BONUS',
                        'HashData'         => '9DDE1AFD673462C49AD5CBEB13139DE550D4F863A34842843270713577659F38C510B0BBF98DE6BCAA4ABDE382B3597672B9E508E67D0941DF26789132E281DE',
                        'HostMsgList'      => null,
                        'RewardInqResult'  => [
                            'RewardList' => null,
                            'ChequeList' => null,
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData'       => [
                    'order_id'             => '20231223D98E',
                    'transaction_id'       => '20231223D98E',
                    'auth_code'            => null,
                    'ref_ret_num'          => '335709663083',
                    'transaction_security' => 'Full 3D Secure',
                    'proc_return_code'     => '12',
                    'md_status'            => '1',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'masked_number'        => '55496087****1500',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'eci'                  => '02',
                    'cavv'                 => 'xgT+4XVHAAAAAAAAAAAAAAAAAAA=',
                    'error_code'           => '12',
                    'error_message'        => 'İşleminizi gerçekleştiremiyoruz.Tekrar deneyiniz',
                    'md_error_message'     => null,
                    'group_id'             => '000082',
                    'batch_num'            => '005546',
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                    'transaction_time'     => null,
                ],
            ],
            '3d_auth_fail_1'             => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'apiversion'            => '512',
                    'authcode'              => '',
                    'cavv'                  => '',
                    'cavvalgorithm'         => '',
                    'clientid'              => '30691298',
                    'customeripaddress'     => '192.168.0.1',
                    'eci'                   => '',
                    'errmsg'                => '',
                    'errorurl'              => 'http://localhost:807/garanti/3d/response.php',
                    'garanticardind'        => '',
                    'hash'                  => 'FD9BF014BFBC3D977B123AE84247CE3F639913644429304C4EE108C0F40212853628CAECAADB798EE73D467F50C3B5D90FE0F3B921EDAA94B6E2EA888F9FE9B7',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '30691298202409078CF30aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkMcb8vBvm21xQz107TS6WKHVjLlrZP9AMx7KFu8jZRZA3WtZxMUuaUynWLxQGsVrw1vTKIofUQ0dw2P/jVVvPMX/RxI7Bpjvo/pZp3Nmbj2wd1W146UhNNmge7eA+hdrNSHuPp7PmjAihyZEPujAi3Q==6ga2Y3buKZ3ZcJSC7uE6',
                    'hostmsg'               => '',
                    'hostrefnum'            => '',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'MaskedPan'             => '42822090****8012',
                    'md'                    => 'aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkMcb8vBvm21xQz107TS6WKHVjLlrZP9AMx7KFu8jZRZA3WtZxMUuaUynWLxQGsVrw1vTKIofUQ0dw2P/jVVvPMX/RxI7Bpjvo/pZp3Nmbj2wd1W146UhNNmge7eA+hdrNSHuPp7PmjAihyZEPujAi3Q==',
                    'mderrormessage'        => '',
                    'mdstatus'              => '0',
                    'mode'                  => 'TEST',
                    'oid'                   => '202409078CF3',
                    'orderid'               => '202409078CF3',
                    'paressyntaxok'         => '',
                    'paresverified'         => '',
                    'procreturncode'        => '',
                    'response'              => '',
                    'rnd'                   => '6ga2Y3buKZ3ZcJSC7uE6',
                    'secure3dhash'          => '9F3027C22FB3485484144993E5EE0B0B99FB30A7CF76EBF885F0F01BC0898FB54C3892F02FAB1BF8C16B0F8C868A6C6F7689381D0DBC882ABA786EA764B13DCA',
                    'secure3dsecuritylevel' => '3D',
                    'successurl'            => 'http://localhost:807/garanti/3d/response.php',
                    'terminalid'            => '30691298',
                    'terminalmerchantid'    => '7000679',
                    'terminalprovuserid'    => 'PROVAUT',
                    'terminaluserid'        => 'PROVAUT',
                    'transid'               => '202409078CF3',
                    'txnamount'             => '1001',
                    'txncurrencycode'       => '949',
                    'txninstallmentcount'   => '',
                    'txnstatus'             => '',
                    'txntype'               => 'sales',
                    'vendorcode'            => '',
                    'version'               => '',
                    'xid'                   => '9df81889-86f6-42bf-9129-8189d30e6fef',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id'             => '202409078CF3',
                    'transaction_id'       => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'transaction_security' => 'MPI fallback',
                    'proc_return_code'     => null,
                    'md_status'            => '0',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'masked_number'        => null,
                    'amount'               => 10.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'eci'                  => null,
                    'cavv'                 => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_error_message'     => null,
                    'batch_num'            => null,
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                    'transaction_time'     => null,
                ],
            ],
            'success1'                   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'xid'                   => '748ac354-4bfe-4b40-aa12-5ea025b7399b',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Y-status/Challenge authentication via ACS: https://gbemv3dsecure.garanti.com.tr/web/creq',
                    'txnstatus'             => null,
                    'eci'                   => '02',
                    'cavv'                  => 'xgRWtC2UAAAAAAAAAAAAAAAAAAA=',
                    'paressyntaxok'         => null,
                    'paresverified'         => null,
                    'version'               => null,
                    'ireqcode'              => null,
                    'ireqdetail'            => null,
                    'vendorcode'            => null,
                    'cavvalgorithm'         => null,
                    'md'                    => 'aW5kZXg6MDJrx8O9qwUvrCPAHSeJG+tDncPcvXkhbmvZPQakkqHX/hMEIzcDkmnDsIBA8BD5zX/aDIAerqJ/h7GIw2VTtNaGjN7JZhmwVSL65/agw5g0JbmcRy40JE3ZjoEvP060kaUVxk66R8U+NJ2jSDj2mYeF ▶',
                    'terminalid'            => '30691298',
                    'oid'                   => '202312238064',
                    'authcode'              => null,
                    'response'              => null,
                    'errmsg'                => null,
                    'hostmsg'               => null,
                    'procreturncode'        => null,
                    'transid'               => '202312238064',
                    'hostrefnum'            => null,
                    'rnd'                   => 'QFEBiW9lrfqK1olQ5UqN',
                    'hash'                  => '7C717431E3763C5C9CCAFE7B905B29A120982D4840DFC61926A5737C0B8BA6D4D00DA1C481E429E12D89D827D09B36074913BAD792A91E95DBFCD3CB68A0FDB5',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '306912982023122380641xgRWtC2UAAAAAAAAAAAAAAAAAAA=02aW5kZXg6MDJrx8O9qwUvrCPAHSeJG+tDncPcvXkhbmvZPQakkqHX/hMEIzcDkmnDsIBA8BD5zX/aDIAerqJ/h7GIw2VTtNaGjN7JZhmwVSL65 ▶',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '55496087****1500',
                    'apiversion'            => '512',
                    'orderid'               => '202312238064',
                    'txninstallmentcount'   => null,
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '8088CAB6FA21AB437D2F9296C0B378D44C7A71CEF3E4854DD3D0376321BA4AB3213813BDBE1F7003F6D8FE4E4D43429D252DF7C130BB03C0411626574C9E2051',
                    'secure3dsecuritylevel' => '3D',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '101',
                    'successurl'            => 'http://localhost/garanti/3d/response.php',
                    'txntype'               => 'sales',
                    'customeripaddress'     => '172.26.0.1',
                ],
                'paymentData'        => [
                    'Mode'        => null,
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '172.26.0.1',
                        'EmailAddress' => null,
                    ],
                    'Order'       => [
                        'OrderID' => '202312238064',
                        'GroupID' => null,
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'HOST',
                            'Code'       => '00',
                            'ReasonCode' => '00',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => null,
                            'SysErrMsg'  => null,
                        ],
                        'RetrefNum'        => '335709663080',
                        'AuthCode'         => '103550',
                        'BatchNum'         => '005546',
                        'SequenceNum'      => '000080',
                        'ProvDate'         => '20231223 19:24:30',
                        'CardNumberMasked' => '55496087****1500',
                        'CardHolderName'   => '4517******* 4517**********',
                        'CardType'         => 'BONUS',
                        'HashData'         => '1724AAE56E9EF08EAF70633AB5F56F55E538A18201A3A98E03D1DDFC4E2A3185FF6421261F96B3F3B052F0090D5CC15F3254051304F0589BD2061F2622B320A0',
                        'HostMsgList'      => null,
                        'RewardInqResult'  => [
                            'RewardList' => null,
                            'ChequeList' => null,
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData'       => [
                    'order_id'             => '202312238064',
                    'transaction_id'       => '202312238064',
                    'auth_code'            => '103550',
                    'ref_ret_num'          => '335709663080',
                    'transaction_security' => 'Full 3D Secure',
                    'proc_return_code'     => '00',
                    'md_status'            => '1',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'masked_number'        => '55496087****1500',
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'tx_status'            => null,
                    'eci'                  => '02',
                    'cavv'                 => 'xgRWtC2UAAAAAAAAAAAAAAAAAAA=',
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_error_message'     => null,
                    'group_id'             => '000080',
                    'batch_num'            => '005546',
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                    'transaction_time'     => new \DateTimeImmutable('2023-12-23 19:24:30'),
                ],
            ],
            'success_with_installment'   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'xid'                   => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Authenticated',
                    'txnstatus'             => 'Y',
                    'eci'                   => '02',
                    'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'paressyntaxok'         => 'true',
                    'paresverified'         => 'true',
                    'version'               => '2.0',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'vendorcode'            => '',
                    'cavvalgorithm'         => '3',
                    'md'                    => 'aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkm5MLNOtFzZ5K914RtN5+rhLhO8yz+cmEUcjkfzNwAnY+eqIM7irJ7LXR+izmjNBOCH8dnD4ZQz+bbPoMp7I8mUMwsTjuV4E4EhyyJaZ93v5675lrk2bb9dFEIcqnzVYt',
                    'terminalid'            => '30691298',
                    'oid'                   => '20240310D17E',
                    'authcode'              => '',
                    'response'              => '',
                    'errmsg'                => '',
                    'hostmsg'               => '',
                    'procreturncode'        => '',
                    'transid'               => '20240310D17E',
                    'hostrefnum'            => '',
                    'rnd'                   => 'WS5m0vq/9QA2vOA1O3l0',
                    'hash'                  => '476399FBB4DA8A50FF6D32A5BF65F5535984413A7625B7DE7EA4E2F61517E8B64E5351F06BA5C76CE6B570E8CBD4453292AD4C37F5B1987DAEE46D07060D46BE',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '3069129820240310D17E1jCm0m+u/0hUfAREHBAMBcfN+pSo=02aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkm5MLNOtFzZ5K914RtN5+rhLhO8yz+cmEUcjkfzNwAnY+eqIM7irJ7LXR+izmjNBOCH8dnD4ZQz+bbPoMp7I8mUMwsTjuV4E4EhyyJaZ93v5675lrk2bb9dFEIcqnzVYtWS5m0vq/9QA2vOA1O3l0',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '42822090****8015',
                    'apiversion'            => '512',
                    'orderid'               => '20240310D17E',
                    'txninstallmentcount'   => '3',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '825488C55839B017499AB69353FE3774F25BB14F73BDB7BDBC89D8F5F1AEB491BC629613C174AE975F46A2A4E1B545FCEDE8D9B4464E11706F4F1827DCC04F4F',
                    'secure3dsecuritylevel' => '3D',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '101',
                    'successurl'            => 'http://localhost/garanti/3d/response.php',
                    'txntype'               => 'sales',
                    'customeripaddress'     => '172.26.0.1',
                ],
                'paymentData'        => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '172.26.0.1',
                        'EmailAddress' => '',
                    ],
                    'Order'       => [
                        'OrderID' => '20240310D17E',
                        'GroupID' => '',
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'HOST',
                            'Code'       => '00',
                            'ReasonCode' => '00',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '407000985165',
                        'AuthCode'         => '304919',
                        'BatchNum'         => '005717',
                        'SequenceNum'      => '000409',
                        'ProvDate'         => '20240310 14:05:08',
                        'CardNumberMasked' => '42822090****8015',
                        'CardHolderName'   => 'HA*** YIL***',
                        'CardType'         => 'FLEXI',
                        'HashData'         => '6D6CA65F9420861F942E74BBA8C2DCB367150E28F23447394915D518E01808E36AB462C5F2B85B7058447C20CD4FB1FE66B6D7366FE233A762E2D3FC8FD07DC2',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData'       => [
                    'amount'               => 1.01,
                    'auth_code'            => '304919',
                    'batch_num'            => '005717',
                    'cavv'                 => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'currency'             => 'TRY',
                    'eci'                  => '02',
                    'error_code'           => null,
                    'error_message'        => null,
                    'group_id'             => '000409',
                    'installment_count'    => 3,
                    'masked_number'        => '42822090****8015',
                    'md_error_message'     => null,
                    'md_status'            => '1',
                    'order_id'             => '20240310D17E',
                    'payment_model'        => '3d',
                    'proc_return_code'     => '00',
                    'ref_ret_num'          => '407000985165',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'transaction_id'       => '20240310D17E',
                    'transaction_security' => 'Full 3D Secure',
                    'transaction_type'     => 'pay',
                    'tx_status'            => 'Y',
                    'transaction_time'     => new \DateTimeImmutable('2024-03-10 14:05:08'),
                ],
            ],
        ];
    }


    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'     => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'xid'                   => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Authenticated',
                    'txnstatus'             => 'Y',
                    'eci'                   => '02',
                    'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'paressyntaxok'         => 'true',
                    'paresverified'         => 'true',
                    'version'               => '2.0',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'vendorcode'            => '',
                    'cavvalgorithm'         => '3',
                    'md'                    => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51A7ajPU4mKMSU5LSbRZ/DYiHzgrGsFz6Ow7ditodw/u5116kO5t/Gvv4yZ89KOHO06jIquCipc01ocHKHSyQU187XPZksYUFppPDpqjtgAGiQUXRGSuJJRig==',
                    'terminalid'            => '30691298',
                    'oid'                   => '20221101657A',
                    'authcode'              => '304919',
                    'response'              => 'Approved',
                    'errmsg'                => '',
                    'hostmsg'               => '',
                    'procreturncode'        => '00',
                    'transid'               => '20221101657A',
                    'hostrefnum'            => '230508300426',
                    'rnd'                   => 'pfEyUZI0g2djbK4UiqKx',
                    'hash'                  => '76Ga8XKh8ynllnNt2rPkq2Q0Oa4=',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '3069129820221101657A30491900Approved1jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51A7ajPU4mKMSU5LSbRZ/DYiHzgrGsFz6Ow7ditodw/u5116kO5t/Gvv4yZ89KOHO06jIquCipc01ocHKHSyQU187XPZksYUFppPDpqjtgAGiQUXRGSuJJRig==pfEyUZI0g2djbK4UiqKx',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '428220***8015',
                    'apiversion'            => 'v0.01',
                    'orderid'               => '20221101657A',
                    'txninstallmentcount'   => '2',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '06A4AA2C344F7F1E1CF7610E64797D9282A0D638',
                    'secure3dsecuritylevel' => '3D_PAY',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d-pay/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '101',
                    'successurl'            => 'http://localhost/garanti/3d-pay/response.php',
                    'customeripaddress'     => '172.26.0.1',
                    'txntype'               => 'sales',
                ],
                'expectedData' => [
                    'order_id'             => '20221101657A',
                    'transaction_id'       => '20221101657A',
                    'auth_code'            => '304919',
                    'ref_ret_num'          => '230508300426',
                    'batch_num'            => null,
                    'transaction_security' => 'Full 3D Secure',
                    'proc_return_code'     => '00',
                    'md_status'            => '1',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'masked_number'        => '428220***8015',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Y',
                    'eci'                  => '02',
                    'cavv'                 => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_error_message'     => null,
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d_pay',
                    'installment_count'    => 2,
                    'transaction_time'     => new \DateTimeImmutable(),
                ],
            ],
            'authFail'     => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'mdstatus'              => '7',
                    'mderrormessage'        => 'Sistem Hatasi',
                    'errmsg'                => 'Sistem Hatasi',
                    'clientid'              => '30691298',
                    'oid'                   => '2022110159A0',
                    'response'              => 'Error',
                    'procreturncode'        => '99',
                    'apiversion'            => 'v0.01',
                    'orderid'               => '2022110159A0',
                    'txninstallmentcount'   => '',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '8C191C2BB01B2E77DAF0CD71436001E561A8ED56',
                    'secure3dsecuritylevel' => '3D_PAY',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d-pay/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '0',
                    'successurl'            => 'http://localhost/garanti/3d-pay/response.php',
                    'customeripaddress'     => '172.26.0.1',
                    'txntype'               => 'sales',
                    'terminalid'            => '30691298',
                ],
                'expectedData' => [
                    'order_id'             => '2022110159A0',
                    'transaction_id'       => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'transaction_security' => 'MPI fallback',
                    'proc_return_code'     => '99',
                    'md_status'            => '7',
                    'status'               => 'declined',
                    'status_detail'        => 'general_error',
                    'masked_number'        => null,
                    'amount'               => 0.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'eci'                  => null,
                    'cavv'                 => null,
                    'error_code'           => '99',
                    'error_message'        => 'Sistem Hatasi',
                    'md_error_message'     => 'Sistem Hatasi',
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d_pay',
                    'installment_count'    => 0,
                    'transaction_time'     => null,
                ],
            ],
            'paymentFail1' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'xid'                   => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Authenticated',
                    'txnstatus'             => 'Y',
                    'eci'                   => '02',
                    'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'paressyntaxok'         => 'true',
                    'paresverified'         => 'true',
                    'version'               => '2.0',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'vendorcode'            => '',
                    'cavvalgorithm'         => '3',
                    'md'                    => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kP0D9xGRZDbWgvgfOt1WTolrbReg1xoLFLHlZ6uZPLz/t34VcRCRNzRuGqMkRi/+r0cKRbNRVp5TJPl7blsqS8ykvTCtee14fMPIv0bohT6A==',
                    'terminalid'            => '30691298',
                    'oid'                   => '20221101A0F9',
                    'authcode'              => ' ',
                    'response'              => 'Declined',
                    'errmsg'                => 'TxnAmount field must not be zero DOUBLE value because of the Mandatory Rule:zero',
                    'hostmsg'               => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                    'procreturncode'        => '92',
                    'transid'               => '20221101A0F9',
                    'hostrefnum'            => '',
                    'rnd'                   => 's5sBV3uf+DTkj2PXQc2/',
                    'hash'                  => 'F7iI4nt48tiTs5OSfOSeXy325v8=',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '3069129820221101A0F9 92Declined1jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kP0D9xGRZDbWgvgfOt1WTolrbReg1xoLFLHlZ6uZPLz/t34VcRCRNzRuGqMkRi/+r0cKRbNRVp5TJPl7blsqS8ykvTCtee14fMPIv0bohT6A==s5sBV3uf+DTkj2PXQc2/',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '428220***8015',
                    'apiversion'            => 'v0.01',
                    'orderid'               => '20221101A0F9',
                    'txninstallmentcount'   => '',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => '65CA0086F1859CF01CC3CE692B20E432853B35E7',
                    'secure3dsecuritylevel' => '3D_PAY',
                    'txncurrencycode'       => '949',
                    'errorurl'              => 'http://localhost/garanti/3d-pay/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '0',
                    'successurl'            => 'http://localhost/garanti/3d-pay/response.php',
                    'customeripaddress'     => '172.26.0.1',
                    'txntype'               => 'sales',
                ],
                'expectedData' => [
                    'order_id'             => '20221101A0F9',
                    'transaction_id'       => '20221101A0F9',
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'transaction_security' => 'Full 3D Secure',
                    'proc_return_code'     => '92',
                    'md_status'            => '1',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_transaction',
                    'masked_number'        => '428220***8015',
                    'amount'               => 0.0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Y',
                    'eci'                  => '02',
                    'cavv'                 => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'error_code'           => '92',
                    'error_message'        => 'TxnAmount field must not be zero DOUBLE value because of the Mandatory Rule:zero',
                    'md_error_message'     => null,
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d_pay',
                    'installment_count'    => 0,
                    'transaction_time'     => null,
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): array
    {
        return [
            'success_pay'     => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID'        => '20221101EB13',
                        'GroupID'        => '',
                        'OrderInqResult' => [
                            // bu kisimdaki veriler baska response'dan alindi
                            'ChargeType'         => 'S',
                            'PreAuthAmount'      => '0',
                            'PreAuthDate'        => '',
                            'AuthAmount'         => '101',
                            'AuthDate'           => '2023-01-07 21:27:59.271',
                            'RecurringInfo'      => 'N',
                            'RecurringStatus'    => '',
                            'Status'             => 'APPROVED',
                            'RemainingBNSAmount' => '0',
                            'UsedFBBAmount'      => '0',
                            'UsedChequeType'     => '',
                            'UsedChequeCount'    => '0',
                            'UsedChequeAmount'   => '0',
                            'UsedBnsAmount'      => '0',
                            'InstallmentCnt'     => '0',
                            'CardNumberMasked'   => '428220******8015',
                            'CardRef'            => '',
                            'Code'               => '00',
                            'ReasonCode'         => '00',
                            'SysErrMsg'          => '',
                            'RetrefNum'          => '300708704369',
                            'GPID'               => '',
                            'AuthCode'           => '304919',
                            'BatchNum'           => '5168',
                            'SequenceNum'        => '21',
                            'ProvDate'           => '2023-01-07 21:27:59.253',
                            'CardHolderName'     => 'HA*** YIL***',
                            'CardType'           => 'FLEXI',
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'HOST',
                            'Code'       => '00',
                            'ReasonCode' => '00',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '230508300896',
                        'AuthCode'         => '304919',
                        'BatchNum'         => '004951',
                        'SequenceNum'      => '000026',
                        'ProvDate'         => '20221101 15:56:43',
                        'CardNumberMasked' => '428220******8015',
                        'CardHolderName'   => 'HA*** YIL***',
                        'CardType'         => 'FLEXI',
                        'HashData'         => '6A03BADEA1D76DEB1C8014E07E5ADFAFE3E07F3C',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                        'GarantiCardInd'   => 'Y',
                    ],
                ],
                'expectedData' => [
                    'order_id'          => '20221101EB13',
                    'auth_code'         => '304919',
                    'proc_return_code'  => '00',
                    'transaction_id'    => null,
                    'transaction_time'  => new \DateTimeImmutable('2023-01-07 21:27:59.253'),
                    'capture_time'      => new \DateTimeImmutable('2023-01-07 21:27:59.271'),
                    'error_message'     => null,
                    'ref_ret_num'       => '300708704369',
                    'order_status'      => 'APPROVED',
                    'transaction_type'  => null,
                    'first_amount'      => 1.01,
                    'capture_amount'    => 1.01,
                    'status'            => 'approved',
                    'error_code'        => null,
                    'status_detail'     => 'approved',
                    'capture'           => true,
                    'currency'          => null,
                    'masked_number'     => '428220******8015',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => 0,
                ],
            ],
            'success_pre_pay' => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '127.0.0.1',
                        'EmailAddress' => '',
                    ],
                    'Order'       => [
                        'OrderID'        => '2024010649DF',
                        'GroupID'        => '',
                        'OrderInqResult' => [
                            'ChargeType'         => 'S',
                            'PreAuthAmount'      => '101',
                            'PreAuthDate'        => '2024-01-06 23:10:05.975',
                            'AuthAmount'         => '0',
                            'AuthDate'           => '',
                            'RecurringInfo'      => 'N',
                            'RecurringStatus'    => '',
                            'Status'             => 'WAITINGPOSTAUTH',
                            'RemainingBNSAmount' => '0',
                            'UsedFBBAmount'      => '0',
                            'UsedChequeType'     => '',
                            'UsedChequeCount'    => '0',
                            'UsedChequeAmount'   => '0',
                            'UsedBnsAmount'      => '0',
                            'InstallmentCnt'     => '3',
                            'CardNumberMasked'   => '37562400****036',
                            'CardRef'            => '',
                            'Code'               => '00',
                            'ReasonCode'         => '00',
                            'SysErrMsg'          => '',
                            'RetrefNum'          => '400609699313',
                            'GPID'               => '',
                            'AuthCode'           => '257762',
                            'BatchNum'           => '5562',
                            'SequenceNum'        => '57',
                            'ProvDate'           => '2024-01-06 23:10:06.029',
                            'CardHolderName'     => 'UT** ER***',
                            'CardType'           => 'AMEXP',
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '00',
                            'ReasonCode' => '',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20240106 23:10:14',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => '7A30CEC427029F563C74AFF7DAE5C2B81915E8246F9F087214AB7BEF63271F7BAC21BD39B80222A77BDDAD3CDA9B5E030D719A387FE18CC7F73FCC86E8B8FC2E',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'          => '2024010649DF',
                    'auth_code'         => '257762',
                    'proc_return_code'  => '00',
                    'transaction_id'    => null,
                    'transaction_time'  => new \DateTimeImmutable('2024-01-06 23:10:06.029'),
                    'capture_time'      => null,
                    'error_message'     => null,
                    'ref_ret_num'       => '400609699313',
                    'order_status'      => 'PRE_AUTH_COMPLETED',
                    'transaction_type'  => null,
                    'first_amount'      => 1.01,
                    'capture_amount'    => 0.0,
                    'status'            => 'approved',
                    'error_code'        => null,
                    'status_detail'     => 'approved',
                    'capture'           => false,
                    'currency'          => null,
                    'masked_number'     => '37562400****036',
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => 3,
                ],
            ],
            'fail1'           => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID'        => '20221101295D',
                        'GroupID'        => '',
                        'OrderInqResult' => [
                            'ChargeType'         => '',
                            'PreAuthAmount'      => '0',
                            'PreAuthDate'        => '',
                            'AuthAmount'         => '0',
                            'AuthDate'           => '',
                            'RecurringInfo'      => '',
                            'RecurringStatus'    => '',
                            'Status'             => '',
                            'RemainingBNSAmount' => '0',
                            'UsedFBBAmount'      => '0',
                            'UsedChequeType'     => '',
                            'UsedChequeCount'    => '0',
                            'UsedChequeAmount'   => '0',
                            'UsedBnsAmount'      => '0',
                            'InstallmentCnt'     => '0',
                            'CardNumberMasked'   => 'null',
                            'CardRef'            => '',
                            'Code'               => '',
                            'ReasonCode'         => '',
                            'SysErrMsg'          => '',
                            'RetrefNum'          => '',
                            'GPID'               => '',
                            'AuthCode'           => '',
                            'BatchNum'           => '0',
                            'SequenceNum'        => '0',
                            'ProvDate'           => '',
                            'CardHolderName'     => '',
                            'CardType'           => '',
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '92',
                            'ReasonCode' => '0110',
                            'Message'    => 'Declined',
                            'ErrorMsg'   => 'İşlem bulunamadı',
                            'SysErrMsg'  => 'ErrorId: 0110',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20221101 15:50:44',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => '2C5E7171202254F3A721166A2F8D4C1EE9582C13',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'          => '20221101295D',
                    'auth_code'         => null,
                    'proc_return_code'  => '92',
                    'transaction_id'    => null,
                    'transaction_time'  => null,
                    'capture_time'      => null,
                    'error_message'     => 'İşlem bulunamadı',
                    'ref_ret_num'       => null,
                    'order_status'      => null,
                    'transaction_type'  => null,
                    'first_amount'      => null,
                    'capture_amount'    => null,
                    'status'            => 'declined',
                    'error_code'        => '92',
                    'status_detail'     => 'invalid_transaction',
                    'capture'           => null,
                    'currency'          => null,
                    'masked_number'     => null,
                    'cancel_time'       => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'installment_count' => 0,
                ],
            ],
        ];
    }

    public function cancelTestDataProvider(): array
    {
        return
            [
                'fail1' => [
                    'responseData' => [
                        'Mode'        => '',
                        'Terminal'    => [
                            'ProvUserID' => 'PROVRFN',
                            'UserID'     => 'PROVRFN',
                            'ID'         => '30691298',
                            'MerchantID' => '7000679',
                        ],
                        'Customer'    => [
                            'IPAddress' => '172.26.0.1',
                        ],
                        'Order'       => [
                            'OrderID' => '20221101C9B8',
                            'GroupID' => '',
                        ],
                        'Transaction' => [
                            'Response'         => [
                                'Source'     => 'HOST',
                                'Code'       => '05',
                                'ReasonCode' => '05',
                                'Message'    => 'Declined',
                                'ErrorMsg'   => 'İşleminizi gerçekleştiremiyoruz.Tekrar deneyiniz',
                                'SysErrMsg'  => 'RPC-05 condition was raised',
                            ],
                            'RetrefNum'        => '230508300968',
                            'AuthCode'         => '304919',
                            'BatchNum'         => '004951',
                            'SequenceNum'      => '000033',
                            'ProvDate'         => '20221101 16:22:11',
                            'CardNumberMasked' => '428220******8015',
                            'CardHolderName'   => '',
                            'CardType'         => '',
                            'HashData'         => '5820A79661E0B894407469B4F764BD58BDC270F1',
                            'HostMsgList'      => '',
                            'RewardInqResult'  => [
                                'RewardList' => '',
                                'ChequeList' => '',
                            ],
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20221101C9B8',
                        'group_id'         => null,
                        'transaction_id'   => null,
                        'auth_code'        => '304919',
                        'ref_ret_num'      => '230508300968',
                        'proc_return_code' => '05',
                        'error_code'       => '05',
                        'error_message'    => 'İşleminizi gerçekleştiremiyoruz.Tekrar deneyiniz',
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
                        'Mode'        => '',
                        'Terminal'    => [
                            'ProvUserID' => 'PROVRFN',
                            'UserID'     => 'PROVRFN',
                            'ID'         => '30691298',
                            'MerchantID' => '7000679',
                        ],
                        'Customer'    => [
                            'IPAddress' => '172.26.0.1',
                        ],
                        'Order'       => [
                            'OrderID' => '20221101EB13',
                            'GroupID' => '',
                        ],
                        'Transaction' => [
                            'Response'         => [
                                'Source'     => 'GVPS',
                                'Code'       => '92',
                                'ReasonCode' => '0208',
                                'Message'    => 'Declined',
                                'ErrorMsg'   => 'İade etmek istediğiniz işlem geçerli değil',
                                'SysErrMsg'  => 'ErrorId: 0208',
                            ],
                            'RetrefNum'        => '230508300918',
                            'AuthCode'         => '',
                            'BatchNum'         => '004951',
                            'SequenceNum'      => '000028',
                            'ProvDate'         => '20221101 16:01:45',
                            'CardNumberMasked' => '',
                            'CardHolderName'   => '',
                            'CardType'         => '',
                            'HashData'         => 'B565B6FF2D9B5C3D36B4CC92459EB92B77886DCC',
                            'HostMsgList'      => '',
                            'RewardInqResult'  => [
                                'RewardList' => '',
                                'ChequeList' => '',
                            ],
                        ],
                    ],
                    'expectedData' => [
                        'order_id'         => '20221101EB13',
                        'group_id'         => null,
                        'transaction_id'   => null,
                        'auth_code'        => null,
                        'ref_ret_num'      => '230508300918',
                        'proc_return_code' => '92',
                        'error_code'       => '92',
                        'error_message'    => 'İade etmek istediğiniz işlem geçerli değil',
                        'status'           => 'declined',
                        'status_detail'    => 'invalid_transaction',
                    ],
                ],
            ];
    }

    public function orderHistoryTestDataProvider(): array
    {
        return [
            'success_single_pay_tx' => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '127.0.0.1',
                        'EmailAddress' => '',
                    ],
                    'Order'       => [
                        'OrderID'            => '2024010662F8',
                        'GroupID'            => '',
                        'OrderHistInqResult' => [
                            'OrderTxnList' => [
                                'OrderTxn' => [
                                    'Type'               => 'sales',
                                    'Status'             => '00',
                                    'PreAuthAmount'      => '0',
                                    'AuthAmount'         => '101',
                                    'PreAuthDate'        => '',
                                    'AuthDate'           => '20240107',
                                    'VoidDate'           => '',
                                    'RetrefNum'          => '400709699645',
                                    'AuthCode'           => '826886',
                                    'ReturnCode'         => '00',
                                    'BatchNum'           => '5562',
                                    'RemainingBNSAmount' => '0',
                                    'UsedFBBAmount'      => '0',
                                    'UsedChequeType'     => '',
                                    'UsedChequeCount'    => '0',
                                    'UsedChequeAmount'   => '0',
                                    'InstallmentCnt'     => '0',
                                    'CurrencyCode'       => '949',
                                    'Settlement'         => 'N',
                                ],
                            ],
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '00',
                            'ReasonCode' => '',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20240107 01:10:11',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => 'F9F6806D2C3CEA2CB25F894F6BA1FD04274B9A47A15A4A7BE5150BB5802835ADE7ADD27F80B275F837B143644B853EE3AD212EB7D7C5FD5708183C93B0852DC0',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '2024010662F8',
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => '826886',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('20240107T000000'),
                            'capture_time'     => new \DateTimeImmutable('20240107T000000'),
                            'error_message'    => null,
                            'ref_ret_num'      => '400709699645',
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'success_multi_tx'      => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '172.26.0.1',
                    ],
                    'Order'       => [
                        'OrderID'            => '20221101EB13',
                        'GroupID'            => '',
                        'OrderHistInqResult' => [
                            'OrderTxnList' => [
                                'OrderTxn' => [
                                    [
                                        'Type'               => 'sales',
                                        'Status'             => '00',
                                        'PreAuthAmount'      => '0',
                                        'AuthAmount'         => '101',
                                        'PreAuthDate'        => '',
                                        'AuthDate'           => '20221101',
                                        'VoidDate'           => '',
                                        'RetrefNum'          => '230508300896',
                                        'AuthCode'           => '304919',
                                        'ReturnCode'         => '00',
                                        'BatchNum'           => '4951',
                                        'RemainingBNSAmount' => '0',
                                        'UsedFBBAmount'      => '0',
                                        'UsedChequeType'     => '',
                                        'UsedChequeCount'    => '0',
                                        'UsedChequeAmount'   => '0',
                                        'CurrencyCode'       => '949',
                                        'Settlement'         => 'N',
                                    ],
                                    [
                                        'Type'               => 'refund',
                                        'Status'             => '01',
                                        'PreAuthAmount'      => '0',
                                        'AuthAmount'         => '101',
                                        'PreAuthDate'        => '',
                                        'AuthDate'           => '',
                                        'VoidDate'           => '',
                                        'RetrefNum'          => '230508300913',
                                        'AuthCode'           => '',
                                        'ReturnCode'         => '92',
                                        'BatchNum'           => '4951',
                                        'RemainingBNSAmount' => '0',
                                        'UsedFBBAmount'      => '0',
                                        'UsedChequeType'     => '',
                                        'UsedChequeCount'    => '0',
                                        'UsedChequeAmount'   => '0',
                                        'CurrencyCode'       => '0',
                                        'Settlement'         => 'N',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '00',
                            'ReasonCode' => '',
                            'Message'    => 'Approved',
                            'ErrorMsg'   => '',
                            'SysErrMsg'  => '',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20221101 16:11:30',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => 'F7FB1830A48C729CD18DFDB47F2B6E2CB8258F21',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '20221101EB13',
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'        => '304919',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('20221101T000000'),
                            'capture_time'     => new \DateTimeImmutable('20221101T000000'),
                            'error_message'    => null,
                            'ref_ret_num'      => '230508300896',
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => '01',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => '230508300913',
                            'order_status'     => null,
                            'transaction_type' => 'refund',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => '01',
                            'status_detail'    => 'bank_call',
                            'capture'          => null,
                            'currency'         => null,
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'fail_order_not_found'  => [
                'responseData' => [
                    'Mode'        => '',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '127.0.0.1',
                        'EmailAddress' => '',
                    ],
                    'Order'       => [
                        'OrderID'            => '202401010C20',
                        'GroupID'            => '',
                        'OrderHistInqResult' => [
                            'OrderTxnList' => '',
                        ],
                    ],
                    'Transaction' => [
                        'Response'         => [
                            'Source'     => 'GVPS',
                            'Code'       => '92',
                            'ReasonCode' => '0108',
                            'Message'    => 'Declined',
                            'ErrorMsg'   => 'Gönderilen sipariş numarasına ait kayıt bulunmamaktadır',
                            'SysErrMsg'  => 'ErrorId: 0108',
                        ],
                        'RetrefNum'        => '',
                        'AuthCode'         => '',
                        'BatchNum'         => '',
                        'SequenceNum'      => '',
                        'ProvDate'         => '20240107 00:13:34',
                        'CardNumberMasked' => '',
                        'CardHolderName'   => '',
                        'CardType'         => '',
                        'HashData'         => 'AC81A642617438C814095A8D07EB6F89118AB5937D2E784AD058CD099E7B50518F894EAD0B4BE6C6793F83B2C0274166A94E99D5545828D2A01BF583BF99D350',
                        'HostMsgList'      => '',
                        'RewardInqResult'  => [
                            'RewardList' => '',
                            'ChequeList' => '',
                        ],
                    ],
                ],
                'expectedData' => [
                    'order_id'         => '202401010C20',
                    'proc_return_code' => '92',
                    'error_code'       => '92',
                    'error_message'    => 'Gönderilen sipariş numarasına ait kayıt bulunmamaktadır',
                    'status'           => 'declined',
                    'status_detail'    => 'invalid_transaction',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
        ];
    }

    public static function historyTestDataProvider(): \Generator
    {
        $dateRangeHistoryExpected = \json_decode(\file_get_contents(__DIR__.'/../../test_data/garanti/history/daily_range_history_expected.json'), true);

        foreach ($dateRangeHistoryExpected['transactions'] as &$item) {
            if (null !== $item['transaction_time']) {
                $item['transaction_time'] = new \DateTimeImmutable(
                    $item['transaction_time']['date'],
                    new \DateTimeZone($item['transaction_time']['timezone'])
                );
            }

            if (null !== $item['capture_time']) {
                $item['capture_time'] = new \DateTimeImmutable(
                    $item['capture_time']['date'],
                    new \DateTimeZone($item['capture_time']['timezone'])
                );
            }
        }

        yield 'success_data_range_history' => [
            'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/garanti/history/date_range_history.json'), true),
            //'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../../../var/garanti-last-2-year-history.json'), true),
            'expectedData' => $dateRangeHistoryExpected,
        ];
        yield 'success_single_transaction' => [
            'responseData' => [
                'Mode'        => '',
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress'    => '172.26.0.1',
                    'EmailAddress' => '',
                ],
                'Order'       => [
                    'OrderID'            => '',
                    'GroupID'            => '',
                    'OrderListInqResult' => [
                        'OrderTxnList' => [
                            'TotalTxnCount'  => '1',
                            'TotalPageCount' => '1',
                            'ActPageNum'     => '1',
                            'OrderTxn'       => [
                                'Id'                       => '1',
                                'LastTrxDate'              => '2024-06-03 16:06:29',
                                'TrxType'                  => 'Satis',
                                'OrderID'                  => '202406036C78',
                                'Name'                     => '',
                                'CardNumberMasked'         => '42822090****8015',
                                'ExpireDate'               => '0830',
                                'BankBin'                  => '42822090',
                                'BatchNum'                 => '576200',
                                'AuthCode'                 => '304919',
                                'RetrefNum'                => '415501677066',
                                'OrigRetrefNum'            => '',
                                'InstallmentCnt'           => 'Pesin',
                                'Status'                   => 'Basarili',
                                'AuthAmount'               => '1001',
                                'CurrencyCode'             => 'TL',
                                'RemainingBNSAmount'       => '0',
                                'UsedFBBAmount'            => '0',
                                'UsedChequeType'           => '',
                                'UsedChequeCount'          => '0',
                                'UsedChequeAmount'         => '0',
                                'SafeType'                 => '',
                                'Comment1'                 => '',
                                'Comment2'                 => '',
                                'Comment3'                 => '',
                                'UserId'                   => 'PROVAUT',
                                'Settlement'               => 'N',
                                'EmailAddress'             => '',
                                'RecurringTotalPaymentNum' => '0',
                                'RecurringLastPaymentNum'  => '0',
                                'RecurringTxnAmount'       => '0',
                                'ResponseCode'             => '00',
                                'SysErrMsg'                => '',
                            ],
                        ],
                    ],
                ],
                'Transaction' => [
                    'Response'         => [
                        'Source'     => 'GVPS',
                        'Code'       => '00',
                        'ReasonCode' => '',
                        'Message'    => 'Approved',
                        'ErrorMsg'   => '',
                        'SysErrMsg'  => '',
                    ],
                    'RetrefNum'        => '',
                    'AuthCode'         => '',
                    'BatchNum'         => '',
                    'SequenceNum'      => '',
                    'ProvDate'         => '20240603 16:07:07',
                    'CardNumberMasked' => '',
                    'CardHolderName'   => '',
                    'CardType'         => '',
                    'HashData'         => 'C1DD90277E3CE36D6226FF02E59D95999D78793CF8942860600BA2800A63CB991A518C1DECA1609C99DA8F8995CBB78A54E7D34F337A8BFF60D10B6DB47C8750',
                    'HostMsgList'      => '',
                    'RewardInqResult'  => [
                        'RewardList' => '',
                        'ChequeList' => '',
                    ],
                ],
            ],
            'expectedData' => [
                'proc_return_code' => '00',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => 'approved',
                'trans_count'      => 1,
                'transactions'     => [
                    [
                        'auth_code'         => '304919',
                        'proc_return_code'  => '00',
                        'transaction_id'    => null,
                        'transaction_time'  => new \DateTimeImmutable('2024-06-03 16:06:29'),
                        'capture_time'      => new \DateTimeImmutable('2024-06-03 16:06:29'),
                        'error_message'     => null,
                        'ref_ret_num'       => '415501677066',
                        'order_status'      => 'PAYMENT_COMPLETED',
                        'transaction_type'  => 'pay',
                        'first_amount'      => 10.01,
                        'capture_amount'    => 10.01,
                        'status'            => 'approved',
                        'error_code'        => null,
                        'status_detail'     => 'approved',
                        'capture'           => true,
                        'currency'          => 'TRY',
                        'masked_number'     => '42822090****8015',
                        'order_id'          => '202406036C78',
                        'batch_num'         => '576200',
                        'payment_model'     => 'regular',
                        'installment_count' => 0,
                    ],
                ],
            ],
        ];
        yield 'fail_invalid_fields' => [
            'responseData' => [
                'Mode'        => '',
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress'    => '',
                    'EmailAddress' => '',
                ],
                'Order'       => [
                    'OrderID'            => '',
                    'GroupID'            => '',
                    'OrderListInqResult' => [
                        'OrderTxnList' => '',
                    ],
                ],
                'Transaction' => [
                    'Response'         => [
                        'Source'     => 'GVPS',
                        'Code'       => '92',
                        'ReasonCode' => '0002',
                        'Message'    => 'Declined',
                        'ErrorMsg'   => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                        'SysErrMsg'  => 'CustomerIPAddress field must contain value because of the Mandatory Rule:null',
                    ],
                    'RetrefNum'        => '',
                    'AuthCode'         => '',
                    'BatchNum'         => '',
                    'SequenceNum'      => '',
                    'ProvDate'         => '20240530 12:53:46',
                    'CardNumberMasked' => '',
                    'CardHolderName'   => '',
                    'CardType'         => '',
                    'HashData'         => '09852B466F45FE00769BE0E40F028FFF7B560CCA5871F8E562B910C2E9CEF9972A8C7F8655D67D1E31B24E81BF57F5B35F8446A94591256DCFEB92D551FEC858',
                    'HostMsgList'      => '',
                    'RewardInqResult'  => [
                        'RewardList' => '',
                        'ChequeList' => '',
                    ],
                ],
            ],
            'expectedData' => [
                'proc_return_code' => '92',
                'error_code'       => '92',
                'error_message'    => 'Giriş yaptığınız işlem tipi için zorunlu alanları kontrol ediniz',
                'status'           => 'declined',
                'status_detail'    => 'invalid_transaction',
                'trans_count'      => 0,
                'transactions'     => [],
            ],
        ];
    }
}
