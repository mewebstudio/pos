<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class ParamPosResponseDataMapperTest extends TestCase
{
    private ParamPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new ParamPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new ParamPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, false]
     * ["", false]
     * ["2", false]
     * ["3", false]
     * ["4", false]
     * ["7", false]
     * ["1", true]
     * ["0", false]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"mdStatus": "1"}, "1"]
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
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $order, string $txType, array $threeDResponseData, array $paymentResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order,
        );
        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        if ([] !== $paymentResponse) {
            $this->assertIsArray($actualData['all']);
            $this->assertNotEmpty($actualData['all']);
        }

        $this->assertArrayHasKey('3d_all', $actualData);
        $this->assertIsArray($actualData['3d_all']);
        if ([] !== $threeDResponseData) {
            $this->assertNotEmpty($actualData['3d_all']);
        }

        unset($actualData['all'], $actualData['3d_all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
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
        unset($actualData['cancel_time'], $expectedData['cancel_time']);
        unset($actualData['refund_time'], $expectedData['refund_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);

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

    public function testMapOrderHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
    }

    /**
     * @dataProvider historyTestDataProviderFail
     */
    public function testMapHistoryResponseFail(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);

        $this->assertCount($actualData['trans_count'], $actualData['transactions']);

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
     * @dataProvider historyTestDataProviderSuccess
     */
    public function testMapHistoryResponseSuccess(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);

        $actualData = \json_decode(\json_encode($actualData), true);

        $this->assertSame(
            json_encode($expectedData),
            json_encode($actualData)
        );

        if (count($actualData['transactions']) > 1
            && null !== $actualData['transactions'][0]['transaction_time']
            && null !== $actualData['transactions'][1]['transaction_time']
        ) {
            $this->assertGreaterThan(
                $actualData['transactions'][0]['transaction_time'],
                $actualData['transactions'][1]['transaction_time']
            );
        }
    }

    public static function paymentTestDataProvider(): iterable
    {
        yield 'success_foreign_currency' => [
            'order'        => [
                'id'       => '2025010408A0',
                'currency' => PosInterface::CURRENCY_USD,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'TP_Islem_Odeme_WDResponse' => [
                    'TP_Islem_Odeme_WDResult' => [
                        'Islem_ID'        => '3007300589',
                        'UCD_URL'         => 'NONSECURE',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Banka_Sonuc_Kod' => '0',
                        'Komisyon_Oran'   => '1.75',
                    ],
                ],
            ],
            'expectedData' => [
                'amount'            => 1.01,
                'auth_code'         => null,
                'batch_num'         => null,
                'currency'          => 'USD',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
                'order_id'          => '2025010408A0',
                'payment_model'     => 'regular',
                'proc_return_code'  => 1,
                'ref_ret_num'       => null,
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(),
            ],
        ];
        yield 'success1' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '3007296556',
                        'UCD_HTML'        => 'NONSECURE',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Bank_Trans_ID'   => '25004OjqB07040101',
                        'Bank_AuthCode'   => 'P77950',
                        'Bank_HostMsg'    => '',
                        'Banka_Sonuc_Kod' => '0',
                        'Bank_Extra'      => '<Extra>
    <SETTLEID>15510</SETTLEID>
    <TRXDATE>20250104 14:35:41</TRXDATE>
    <ERRORCODE></ERRORCODE>
    <TERMINALID>0067672</TERMINALID>
    <MERCHANTID>306754300</MERCHANTID>
    <AVSAPPROVALINFORMATION>N</AVSAPPROVALINFORMATION>
    <CARDBRAND>VISA</CARDBRAND>
    <CARDISSUER>T. IS BANKASI A.S.</CARDISSUER>
    <AVSAPPROVE>Y</AVSAPPROVE>
    <HOSTDATE>0104-143542</HOSTDATE>
    <APIHASH>KRCvC47lNU5BZZbG6JyiV2Exjo/DOs6USfpermp8ah3XfNOeIh5gInrOJVABJ0VSQmgAAkSfqCVmVjxmPVGaeg==</APIHASH>
    <APIHASHVERSION>ver2</APIHASHVERSION>
    <AVSAPPROVALMSG>AVS dogrulama yap�l�yor uzunluk yetmedik</AVSAPPROVALMSG>
    <AVSERRORCODEDETAIL>avshatali-avshatali-avshatali-avshatali-</AVSERRORCODEDETAIL>
    <NUMCODE>00</NUMCODE>
  </Extra>',
                        'Siparis_ID'      => '2025010408A0',
                        'Bank_HostRefNum' => '500400109501',
                    ],
                ],
            ],
            'expectedData' => [
                'amount'            => 1.01,
                'auth_code'         => 'P77950',
                'batch_num'         => null,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
                'order_id'          => '2025010408A0',
                'payment_model'     => 'regular',
                'proc_return_code'  => 1,
                'ref_ret_num'       => '500400109501',
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '25004OjqB07040101',
                'transaction_type'  => 'pay',
                'transaction_time'  => new \DateTimeImmutable(),
            ],
        ];
        yield 'success_pre_payment' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'responseData' => [
                'TP_Islem_Odeme_OnProv_WMDResponse' => [
                    'TP_Islem_Odeme_OnProv_WMDResult' => [
                        'Islem_ID'        => '6005034747',
                        'Islem_GUID'      => '72c9b68c-fedc-488e-9166-ae9fc7d4e523',
                        'UCD_HTML'        => 'NONSECURE',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'Ön Provizyon İşlemi Başarılı',
                        'Bank_Trans_ID'   => '21292RsEI18157',
                        'Bank_AuthCode'   => 'P66791',
                        'Bank_HostMsg'    => '',
                        'Banka_Sonuc_Kod' => '0',
                        'Bank_Extra'      => 'bank data',
                        'Siparis_ID'      => '2025010408A0',
                        'Ext_Data'        => 'a|a|a|a|a',
                    ],
                ],
            ],
            'expectedData' => [
                'amount'            => 1.01,
                'auth_code'         => 'P66791',
                'batch_num'         => null,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
                'order_id'          => '2025010408A0',
                'payment_model'     => 'regular',
                'proc_return_code'  => 1,
                'ref_ret_num'       => null,
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '21292RsEI18157',
                'transaction_type'  => 'pre',
                'transaction_time'  => new \DateTimeImmutable(),
            ],
        ];
        yield 'success_post_payment' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
            'responseData' => [
                'TP_Islem_Odeme_OnProv_KapaResponse' => [
                    'TP_Islem_Odeme_OnProv_KapaResult' => [
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'Provizyon Kapama İşlem Başarılı',
                        'Prov_ID'         => 'f7184b1f-c4c2-4d2e-8428-fc6014a00900',
                        'Dekont_ID'       => '6004466311',
                        'Banka_Sonuc_Kod' => '0',
                        'Siparis_ID'      => '20250105CB05',
                        'Bank_Trans_ID'   => '25005RvnD11226',
                        'Bank_AuthCode'   => '519104',
                        'Bank_HostRefNum' => '500517472728',
                        'Bank_Extra'      => 'bank-data',
                        'Bank_HostMsg'    => null,
                    ],
                ],
            ],
            'expectedData' => [
                'amount'            => 1.01,
                'auth_code'         => '519104',
                'batch_num'         => null,
                'currency'          => 'TRY',
                'error_code'        => null,
                'error_message'     => null,
                'installment_count' => null,
                'order_id'          => '20250105CB05',
                'payment_model'     => 'regular',
                'proc_return_code'  => 1,
                'ref_ret_num'       => '500517472728',
                'status'            => 'approved',
                'status_detail'     => null,
                'transaction_id'    => '25005RvnD11226',
                'transaction_type'  => 'post',
                'transaction_time'  => new \DateTimeImmutable(),
            ],
        ];
        yield 'fail_try_again' => [
            'order'        => [
                'currency' => PosInterface::CURRENCY_TRY,
                'amount'   => 1.01,
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '0',
                        'UCD_HTML'        => 'NONSECURE',
                        'Sonuc'           => '-1',
                        'Sonuc_Str'       => 'Tekrar girin, tekrar deneyin.',
                        'Banka_Sonuc_Kod' => '99',
                        'Bank_Extra'      => ''."\n".' '."\n".' 20250104 14:25:24'."\n".' ISO8583-19'."\n".' 99858319'."\n".' ',
                        'Siparis_ID'      => '20250104586F',
                    ],
                ],
            ],
            'expectedData' => [
                'amount'            => null,
                'auth_code'         => null,
                'batch_num'         => null,
                'currency'          => null,
                'error_code'        => -1,
                'error_message'     => 'Tekrar girin, tekrar deneyin.',
                'installment_count' => null,
                'order_id'          => null,
                'payment_model'     => 'regular',
                'proc_return_code'  => -1,
                'ref_ret_num'       => null,
                'status'            => 'declined',
                'status_detail'     => null,
                'transaction_id'    => null,
                'transaction_type'  => 'pay',
                'transaction_time'  => null,
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'success1'                               => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'md'                => '444676:13FDE30917BF65D853787DB838390849D73151A10FC8C1192AC72660F2464521:3473:##190100000',
                    'mdStatus'          => '1',
                    'orderId'           => '202412292160',
                    'transactionAmount' => '10,01',
                    'islemGUID'         => '8b6868fe-a553-4164-a7cc-528461f21759',
                    'islemHash'         => 'jF0PD92E+dM394Z1h5qm4SB6pPo=',
                    'bankResult'        => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/DkaeFfwOZ334oKIk/creq;token=338860451.17354  1',
                    'dc'                => '',
                    'dcURL'             => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentData'        => [
                    'TP_WMD_PayResponse' => [
                        'TP_WMD_PayResult' => [
                            'Sonuc'           => '1',
                            'Sonuc_Ack'       => 'Başarılı',
                            'Dekont_ID'       => '3007295376',
                            'Siparis_ID'      => '202412292160',
                            'UCD_MD'          => '444676:E997FB32A642B0929CCDB822BB3BB2B65050DAAA2E57A31F3E510B8664954C03:3915:##190100000',
                            'Bank_Trans_ID'   => '24364TKkF16602',
                            'Bank_AuthCode'   => '150888',
                            'Bank_HostMsg'    => 'Approved',
                            'Bank_Extra'      => '<Extra>
<SETTLEID>2881</SETTLEID>
<TRXDATE>20241229 19:10:36</TRXDATE>
<ERRORCODE></ERRORCODE>
<CARDBRAND>VISA</CARDBRAND>
<CARDISSUER>Z&#x130;RAAT BANKASI</CARDISSUER>
<KAZANILANPUAN>000000010.00</KAZANILANPUAN>
<NUMCODE>00</NUMCODE>
</Extra>',
                            'Bank_Sonuc_Kod'  => '0',
                            'Bank_HostRefNum' => '436419200463',
                            'Komisyon_Oran'   => '1.01',
                        ],
                    ],
                ],
                'expectedData'       => [
                    'amount'               => 10.01,
                    'auth_code'            => '150888',
                    'batch_num'            => null,
                    'currency'             => 'TRY',
                    'error_code'           => null,
                    'error_message'        => null,
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => '1',
                    'order_id'             => '202412292160',
                    'payment_model'        => '3d',
                    'proc_return_code'     => 1,
                    'ref_ret_num'          => '436419200463',
                    'status'               => 'approved',
                    'status_detail'        => null,
                    'transaction_id'       => '24364TKkF16602',
                    'transaction_security' => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable(),
                ],
            ],
            '3d_auth_fail'                           => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'md'                => '444676:84E83D96A7CEC3A5815D49EB7F64D2709D1BC30425D578D118B9819A81749FB8:4429:##190100000',
                    'mdStatus'          => '0',
                    'orderId'           => '20241229C152',
                    'transactionAmount' => '1000,01',
                    'islemGUID'         => 'c1ee369b-ec27-4ab6-8c27-2e15e62793d3',
                    'islemHash'         => 'N1/W7/GcbuT3UVwVM9Q5C/rmoKg=',
                    'bankResult'        => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/wkwLCHgiNwZCiVZp/creq;token=338863271.17354  0',
                    'dc'                => null,
                    'dcURL'             => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'amount'               => 1000.01,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => 'TRY',
                    'error_code'           => null,
                    'error_message'        => null,
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => '0',
                    'order_id'             => '20241229C152',
                    'payment_model'        => '3d',
                    'proc_return_code'     => null,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_security' => null,
                    'transaction_time'     => null,
                    'transaction_type'     => 'pay',
                ],
            ],
            '3d_auth_success_payment_fail'           => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'md'                => '444676:B1748AA7FF30A96AADFECC19670A3038C1419A842DD221D2408708A84FE9D811:4011:##190100000',
                    'mdStatus'          => '1',
                    'orderId'           => '202412306616',
                    'transactionAmount' => '10,01',
                    'islemGUID'         => '5ee6c14d-94c2-48c9-bfe1-da31c415e647',
                    'islemHash'         => 'zrHcS2iMl0J0GPU6DalIdOppDz8=',
                    'bankResult'        => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/GfPAbJmZtVUkn73n/creq;token=338907941.17355  1',
                    'dc'                => 'd1',
                    'dcURL'             => 'https://test-dmzd1.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentData'        => [
                    'TP_WMD_PayResponse' => [
                        'TP_WMD_PayResult' => [
                            'Sonuc'          => '-100',
                            'Sonuc_Ack'      => 'Hesap bulunamadı.',
                            'Bank_Sonuc_Kod' => '-1',
                            'Komisyon_Oran'  => '0',
                        ],
                    ],
                ],
                'expectedData'       => [
                    'amount'               => 10.01,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => 'TRY',
                    'error_code'           => -100,
                    'error_message'        => 'Hesap bulunamadı.',
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => '1',
                    'order_id'             => '202412306616',
                    'payment_model'        => '3d',
                    'proc_return_code'     => -100,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_security' => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                ],
            ],
            '3d_auth_success_payment_fail_not_found' => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'md'                => '444676:A78A6D388E30776DA8F0EB5920F615F9AA523AC0E1EBA1499DB65C2B2BC4F29B:4474:##190100000',
                    'mdStatus'          => '1',
                    'orderId'           => '20250104E733',
                    'transactionAmount' => '10,01',
                    'islemGUID'         => '45c47ad7-2d2a-4e9f-adca-a9641cd34b74',
                    'islemHash'         => 'TLN7U1O+XAreUpt7nx6UlP63nzc=',
                    'bankResult'        => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/5KExb5V_7OxhntjM/creq;token=339263081.17359  1',
                    'dc'                => null,
                    'dcURL'             => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentData'        => [
                    'TP_WMD_PayResponse' => [
                        'TP_WMD_PayResult' => [
                            'Sonuc'          => '-1',
                            'Sonuc_Ack'      => 'İşlem bilgisi sorgulanamadı.',
                            'Bank_Sonuc_Kod' => '-1',
                            'Komisyon_Oran'  => '0',
                        ],
                    ],
                ],
                'expectedData'       => [
                    'amount'               => 10.01,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => 'TRY',
                    'error_code'           => -1,
                    'error_message'        => 'İşlem bilgisi sorgulanamadı.',
                    'installment_count'    => null,
                    'md_error_message'     => null,
                    'md_status'            => '1',
                    'order_id'             => '20250104E733',
                    'payment_model'        => '3d',
                    'proc_return_code'     => -1,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_security' => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => '1944A39AD0AEA92E173D665B',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '19.01.2025 17:29:32',
                    'TURKPOS_RETVAL_Dekont_ID'         => '3007300695',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '20250119BACB',
                    'TURKPOS_RETVAL_Ext_Data'          => '|||||||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '0',
                    'TURKPOS_RETVAL_PB'                => 'TL',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => new \DateTimeImmutable('19.01.2025 17:29:32'),
                    'transaction_type'     => 'pay',
                    'masked_number'        => '581877******2285',
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'ref_ret_num'          => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '20250119BACB',
                    'proc_return_code'     => 1,
                    'status'               => 'approved',
                    'status_detail'        => null,
                    'payment_model'        => '3d_host',
                    'currency'             => 'TRY',
                    'amount'               => 10.01,
                    'installment_count'    => 0,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],

            'auth_fail' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => 'FF0591BD887935E481743533',
                    'TURKPOS_RETVAL_Sonuc'             => '-1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '19.01.2025 18:53:48',
                    'TURKPOS_RETVAL_Dekont_ID'         => '0',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '202501193584',
                    'TURKPOS_RETVAL_Ext_Data'          => '|||||||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '-1',
                    'TURKPOS_RETVAL_PB'                => 'TL',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => '+qCEIrH+7ARPmJC55MGQCykg8pk=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '93dd02a2-d43b-42aa-83b1-a3fc7d8393ab',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847067',
                ],
                'expectedData' => [
                    'amount'               => null,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => null,
                    'error_code'           => -1,
                    'error_message'        => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',
                    'installment_count'    => null,
                    'order_id'             => null,
                    'payment_model'        => '3d_host',
                    'proc_return_code'     => -1,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): iterable
    {
        yield 'success_payment' => [
            'responseData' => [
                'TP_Islem_Sorgulama4Response' => [
                    'TP_Islem_Sorgulama4Result' => [
                        'DT_Bilgi'  => [
                            'Odeme_Sonuc'          => '1',
                            'Odeme_Sonuc_Aciklama' => 'İşlem Başarılı',
                            'Dekont_ID'            => '3007296662',
                            'Siparis_ID'           => '20250105E324',
                            'Islem_ID'             => '665C5908BA1D7583BDF62D3A',
                            'Durum'                => 'SUCCESS',
                            'Tarih'                => '05.01.2025 13:14:32',
                            'Toplam_Tutar'         => '10.01',
                            'Komisyon_Oran'        => '1.75',
                            'Komisyon_Tutar'       => '0.18',
                            'Banka_Sonuc_Aciklama' => '',
                            'Taksit'               => '1',
                            'Ext_Data'             => '||||',
                            'Toplam_Iade_Tutar'    => '0',
                            'KK_No'                => '581877******2285',
                            'Bank_Extra'           => '<Extra>
    <AUTH_CODE>222905</AUTH_CODE>
    <AUTH_DTTM>2025-01-05 13:14:32.162</AUTH_DTTM>
    <CAPTURE_AMT>1001</CAPTURE_AMT>
    <CAPTURE_DTTM>2025-01-05 13:14:32.162</CAPTURE_DTTM>
    <CAVV_3D></CAVV_3D>
    <CHARGE_TYPE_CD>S</CHARGE_TYPE_CD>
    <ECI_3D></ECI_3D>
    <HOSTDATE>0105-131432</HOSTDATE>
    <HOST_REF_NUM>500513472717</HOST_REF_NUM>
    <MDSTATUS></MDSTATUS>
    <NUMCODE>0</NUMCODE>
    <ORDERSTATUS>ORD_ID:10126021842587	CHARGE_TYPE_CD:S	ORIG_TRANS_AMT:1001	CAPTURE_AMT:1001	TRANS_STAT:C	AUTH_DTTM:2025-01-05 13:14:32.162	CAPTURE_DTTM:2025-01-05 13:14:32.162	AUTH_CODE:222905	TRANS_ID:25005NOgE12061</ORDERSTATUS>
    <ORD_ID>10126021842587</ORD_ID>
    <ORIG_TRANS_AMT>1001</ORIG_TRANS_AMT>
    <PAN>5818 77** **** 2285</PAN>
    <PROC_RET_CD>00</PROC_RET_CD>
    <SETTLEID></SETTLEID>
    <TRANS_ID>25005NOgE12061</TRANS_ID>
    <TRANS_STAT>C</TRANS_STAT>
    <XID_3D></XID_3D>
  </Extra>',
                            'Islem_Tip'            => 'SALE',
                            'Bank_Trans_ID'        => '25005NOgE12061',
                            'Bank_AuthCode'        => '222905',
                            'Bank_HostRefNum'      => '500513472717',
                        ],
                        'Sonuc'     => '1',
                        'Sonuc_Str' => 'Başarılı',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'          => '20250105E324',
                'auth_code'         => '222905',
                'proc_return_code'  => 1,
                'transaction_id'    => '25005NOgE12061',
                'error_message'     => null,
                'ref_ret_num'       => '500513472717',
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'pay',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => 10.01,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => true,
                'transaction_time'  => new \DateTimeImmutable('05.01.2025 13:14:32'),
                'capture_time'      => new \DateTimeImmutable('05.01.2025 13:14:32'),
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        $responseData = json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/status_pay_then_refund_response.json'), true);
        yield 'success_pay_then_refund' => [
            'responseData' => $responseData,
            'expectedData' => [
                'order_id'          => '20250105E324',
                'auth_code'         => '222905',
                'proc_return_code'  => 1,
                'transaction_id'    => '25005NOgE12061',
                'error_message'     => null,
                'ref_ret_num'       => '500513472717',
                'order_status'      => 'FULLY_REFUNDED',
                'transaction_type'  => 'pay',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => 10.01,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => true,
                'transaction_time'  => new \DateTimeImmutable('05.01.2025 13:14:32'),
                'capture_time'      => new \DateTimeImmutable('05.01.2025 13:14:32'),
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        $responseData = json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/status_pay_then_cancel_response.json'), true);
        yield 'success_pay_then_canceled' => [
            'responseData' => $responseData,
            'expectedData' => [
                'order_id'          => '2025011251F7',
                'auth_code'         => '609945',
                'proc_return_code'  => 1,
                'transaction_id'    => '25012UZnE17558',
                'error_message'     => null,
                'ref_ret_num'       => '501220476656',
                'order_status'      => 'CANCELED',
                'transaction_type'  => 'pay',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => 10.01,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => true,
                'transaction_time'  => new \DateTimeImmutable('12.01.2025 20:25:38'),
                'capture_time'      => new \DateTimeImmutable('12.01.2025 20:25:38'),
                'cancel_time'       => new \DateTimeImmutable('12.01.2025 20:25:38'),
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        $responseData = json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/status_pre_pay_response.json'), true);
        yield 'success_pre_pay' => [
            'responseData' => $responseData,
            'expectedData' => [
                'order_id'          => '20250105F546',
                'auth_code'         => '308452',
                'proc_return_code'  => 1,
                'transaction_id'    => '25005QOSI13750',
                'error_message'     => null,
                'ref_ret_num'       => '500516472750',
                'order_status'      => PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED,
                'transaction_type'  => 'pre',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => null,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => false,
                'transaction_time'  => new \DateTimeImmutable('05.01.2025 16:14:18'),
                'capture_time'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        $responseData = json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/status_post_pay_response.json'), true);
        yield 'success_post_pay' => [
            'responseData' => $responseData,
            'expectedData' => [
                'order_id'          => '20250105D70C',
                'auth_code'         => '570335',
                'proc_return_code'  => 1,
                'transaction_id'    => '25005SMEB15694',
                'error_message'     => null,
                'ref_ret_num'       => '500518472771',
                'order_status'      => PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED,
                'transaction_type'  => 'pre',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => null,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => false,
                'transaction_time'  => new \DateTimeImmutable('05.01.2025 18:12:03'),
                'capture_time'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];

        $responseData = json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/status_pre_pay_then_cancel_response.json'), true);
        yield 'success_pre_pay_then_cancel' => [
            'responseData' => $responseData,
            'expectedData' => [
                'order_id'          => '2025010540D6',
                'auth_code'         => '105199',
                'proc_return_code'  => 1,
                'transaction_id'    => '25005SnhJ14709',
                'error_message'     => null,
                'ref_ret_num'       => '500518472763',
                'order_status'      => PosInterface::PAYMENT_STATUS_CANCELED,
                'transaction_type'  => 'pre',
                'masked_number'     => '581877******2285',
                'first_amount'      => 10.01,
                'capture_amount'    => null,
                'currency'          => null,
                'status'            => 'approved',
                'error_code'        => null,
                'status_detail'     => null,
                'capture'           => false,
                'transaction_time'  => new \DateTimeImmutable('05.01.2025 18:39:33'),
                'capture_time'      => null,
                'cancel_time'       => new \DateTimeImmutable('05.01.2025 18:39:33'),
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => null,
            ],
        ];
    }

    public static function cancelTestDataProvider(): iterable
    {
        yield 'success_payment' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'Approved',
                        'Banka_Sonuc_Kod' => '0',
                        'Bank_AuthCode'   => '142436',
                        'Bank_Trans_ID'   => '25005OB6H12275',
                        'Bank_Extra'      => '
<Extra>
<SETTLEID>2976</SETTLEID>
<TRXDATE>20250105 14:01:56</TRXDATE>
<ERRORCODE></ERRORCODE>
<CARDBRAND>MASTERCARD</CARDBRAND>
<CARDISSUER>T. HALK BANKASI A.S.</CARDISSUER>
<CAVVRESULTCODE>3</CAVVRESULTCODE>
<NUMCODE>00</NUMCODE>
</Extra>
          ',
                        'Bank_HostRefNum' => '500514472735',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => '142436',
                'ref_ret_num'      => '500514472735',
                'proc_return_code' => 1,
                'transaction_id'   => '25005OB6H12275',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => null,
            ],
        ];
        yield 'success_pre_pay' => [
            'responseData' => [
                'TP_Islem_Iptal_OnProvResponse' => [
                    'TP_Islem_Iptal_OnProvResult' => [
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'Approved',
                        'Banka_Sonuc_Kod' => '00',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => 1,
                'transaction_id'   => null,
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => null,
            ],
        ];
        yield 'fail_order_not_found_1' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'     => '-210',
                        'Sonuc_Str' => 'İptal/İadeye uygun işlem bulunamadı.',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => -210,
                'transaction_id'   => null,
                'error_code'       => -210,
                'error_message'    => 'İptal/İadeye uygun işlem bulunamadı.',
                'status'           => 'declined',
                'status_detail'    => null,
            ],
        ];
        yield 'fail_already_canceled' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'     => '-211',
                        'Sonuc_Str' => 'İşlem iptal durumunda',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => -211,
                'transaction_id'   => null,
                'error_code'       => -211,
                'error_message'    => 'İşlem iptal durumunda',
                'status'           => 'declined',
                'status_detail'    => null,
            ],
        ];
    }

    public static function refundTestDataProvider(): iterable
    {
        yield 'success_payment' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'Approved',
                        'Banka_Sonuc_Kod' => '0',
                        'Bank_AuthCode'   => '142436',
                        'Bank_Trans_ID'   => '25005OB6H12275',
                        'Bank_Extra'      => '
<Extra>
<SETTLEID>2976</SETTLEID>
<TRXDATE>20250105 14:01:56</TRXDATE>
<ERRORCODE></ERRORCODE>
<CARDBRAND>MASTERCARD</CARDBRAND>
<CARDISSUER>T. HALK BANKASI A.S.</CARDISSUER>
<CAVVRESULTCODE>3</CAVVRESULTCODE>
<NUMCODE>00</NUMCODE>
</Extra>
          ',
                        'Bank_HostRefNum' => '500514472735',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => '142436',
                'ref_ret_num'      => '500514472735',
                'proc_return_code' => 1,
                'transaction_id'   => '25005OB6H12275',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'status_detail'    => null,
            ],
        ];
        yield 'fail_order_not_found_1' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'     => '-210',
                        'Sonuc_Str' => 'İptal/İadeye uygun işlem bulunamadı.',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => -210,
                'transaction_id'   => null,
                'error_code'       => -210,
                'error_message'    => 'İptal/İadeye uygun işlem bulunamadı.',
                'status'           => 'declined',
                'status_detail'    => null,
            ],
        ];
        yield 'fail_refund_twice' => [
            'responseData' => [
                'TP_Islem_Iptal_Iade_Kismi2Response' => [
                    'TP_Islem_Iptal_Iade_Kismi2Result' => [
                        'Sonuc'     => '-221',
                        'Sonuc_Str' => 'İade tutarı, iade edilebilir tutardan büyük olamaz.',
                    ],
                ],
            ],
            'expectedData' => [
                'order_id'         => null,
                'group_id'         => null,
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => -221,
                'transaction_id'   => null,
                'error_code'       => -221,
                'error_message'    => 'İade tutarı, iade edilebilir tutardan büyük olamaz.',
                'status'           => 'declined',
                'status_detail'    => null,
            ],
        ];
    }

    public static function historyTestDataProviderFail(): iterable
    {
        yield 'fail' => [
            'responseData' => [
                'TP_Islem_IzlemeResponse' => [
                    'TP_Islem_IzlemeResult' => [
                        'Sonuc'     => '-1',
                        'Sonuc_Str' => 'Başarısız',
                    ],
                ],
            ],
            'expectedData' => [
                'proc_return_code' => -1,
                'error_code'       => -1,
                'error_message'    => 'Başarısız',
                'status'           => 'declined',
                'status_detail'    => null,
                'trans_count'      => 0,
                'transactions'     => [],
            ],
        ];

        yield 'fail_date_range' => [
            'responseData' => [
                'TP_Islem_IzlemeResponse' => [
                    'TP_Islem_IzlemeResult' => [
                        'Sonuc'     => '-222',
                        'Sonuc_Str' => 'Tarih aralığı 7 günden fazla olamaz.',
                    ],
                ],
            ],
            'expectedData' => [
                'proc_return_code' => -222,
                'error_code'       => -222,
                'error_message'    => 'Tarih aralığı 7 günden fazla olamaz.',
                'status'           => 'declined',
                'status_detail'    => null,
                'trans_count'      => 0,
                'transactions'     => [],
            ],
        ];
    }

    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'                 => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => '1944A39AD0AEA92E173D665B',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '19.01.2025 17:29:32',
                    'TURKPOS_RETVAL_Dekont_ID'         => '3007300695',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '20250119BACB',
                    'TURKPOS_RETVAL_Ext_Data'          => '|||||||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '0',
                    'TURKPOS_RETVAL_PB'                => 'TL',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_time'     => new \DateTimeImmutable('19.01.2025 17:29:32'),
                    'transaction_type'     => 'pay',
                    'masked_number'        => '581877******2285',
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'ref_ret_num'          => null,
                    'error_code'           => null,
                    'error_message'        => null,
                    'order_id'             => '20250119BACB',
                    'proc_return_code'     => 1,
                    'status'               => 'approved',
                    'status_detail'        => null,
                    'payment_model'        => '3d_pay',
                    'currency'             => 'TRY',
                    'amount'               => 10.01,
                    'installment_count'    => 0,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],
            'success_foreign_currency' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_EUR,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => '25B4E0BAAD1F3FC05D46F5B4',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '5.01.2025 14:52:20',
                    'TURKPOS_RETVAL_Dekont_ID'         => '3007296668',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '202501053F4F',
                    'TURKPOS_RETVAL_Ext_Data'          => '||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '0',
                    'TURKPOS_RETVAL_PB'                => 'USD',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'LrFgOcE6S8HzNF4tzvtORAh3C20=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '597b2fc9-df6d-40d7-861a-c4f5d0e94ed3',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021842602',
                ],
                'expectedData' => [
                    'amount'               => 10.01,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => 'USD',
                    'error_code'           => null,
                    'error_message'        => null,
                    'installment_count'    => 0,
                    'masked_number'        => '581877******2285',
                    'order_id'             => '202501053F4F',
                    'payment_model'        => '3d_pay',
                    'proc_return_code'     => 1,
                    'ref_ret_num'          => null,
                    'status'               => 'approved',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('5.01.2025 14:52:20'),
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],
            'fail_foreign_currency'    => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_USD,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => 'C38F20632F9FBBC3FD2A3B35',
                    'TURKPOS_RETVAL_Sonuc'             => '-1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => '3D Dogrulama basarisiz',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '20.01.2025 18:03:23',
                    'TURKPOS_RETVAL_Dekont_ID'         => '0',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '20250120B068',
                    'TURKPOS_RETVAL_Ext_Data'          => '||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '-1',
                    'TURKPOS_RETVAL_PB'                => 'USD',
                    'TURKPOS_RETVAL_KK_No'             => '454671******7894',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'Df3gttv1swwT8N1m3aCVYzp23Rg=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '9ccd323a-9a3e-4a7d-8367-58498fac8fb0',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847392',
                ],
                'expectedData' => [
                    'amount'               => null,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => null,
                    'error_code'           => -1,
                    'error_message'        => '3D Dogrulama basarisiz',
                    'installment_count'    => null,
                    'order_id'             => null,
                    'payment_model'        => '3d_pay',
                    'proc_return_code'     => -1,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],

            'auth_fail' => [
                'order'        => [
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'TURKPOS_RETVAL_Islem_ID'          => 'FF0591BD887935E481743533',
                    'TURKPOS_RETVAL_Sonuc'             => '-1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '19.01.2025 18:53:48',
                    'TURKPOS_RETVAL_Dekont_ID'         => '0',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '202501193584',
                    'TURKPOS_RETVAL_Ext_Data'          => '|||||||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '-1',
                    'TURKPOS_RETVAL_PB'                => 'TL',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => '+qCEIrH+7ARPmJC55MGQCykg8pk=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '93dd02a2-d43b-42aa-83b1-a3fc7d8393ab',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847067',
                ],
                'expectedData' => [
                    'amount'               => null,
                    'auth_code'            => null,
                    'batch_num'            => null,
                    'currency'             => null,
                    'error_code'           => -1,
                    'error_message'        => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',
                    'installment_count'    => null,
                    'order_id'             => null,
                    'payment_model'        => '3d_pay',
                    'proc_return_code'     => -1,
                    'ref_ret_num'          => null,
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'md_error_message'     => null,
                    'md_status'            => null,
                    'transaction_security' => null,
                ],
            ],
        ];
    }


    public static function historyTestDataProviderSuccess(): iterable
    {
        yield 'success' => [
            'responseData' => \json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/history_response_1.json'), true),
            'expectedData' => \json_decode(file_get_contents(__DIR__.'/../../test_data/parampos/history_response_1_expected.json'), true),
        ];
    }
}
