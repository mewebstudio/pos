<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class InterPosResponseDataMapperTest extends TestCase
{
    private InterPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper = new InterPosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );

        $this->responseDataMapper = new InterPosResponseDataMapper(
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
     * [{"3DStatus": "1"}, "1"]
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
        if ([] !== $responseData) {
            $this->assertIsArray($actualData['all']);
            $this->assertNotEmpty($actualData['all']);
        }

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
        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable && $actualData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertSame($expectedData['transaction_time']->format('Ymd'), $actualData['transaction_time']->format('Ymd'));
        } else {
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        }

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

    /**
     * @dataProvider threeDPayPaymentDataProvider
     */
    public function testMap3DPayResponseData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($responseData, $txType, $order);

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

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
    }

    public function testMapOrderHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'Version'        => '',
                    'PurchAmount'    => 320,
                    'Exponent'       => '',
                    'Currency'       => '949',
                    'OkUrl'          => 'https://localhost/pos/examples/interpos/3d/success.php',
                    'FailUrl'        => 'https://localhost/pos/examples/interpos/3d/fail.php',
                    'MD'             => '',
                    'OrderId'        => '20220327140D',
                    'ProcReturnCode' => '81',
                    'Response'       => '',
                    'mdStatus'       => '0',
                    'HASH'           => '9DZVckklZFjuoA7sl4MN0l7VDMo=',
                    'HASHPARAMS'     => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'  => '320949https://localhost/pos/examples/interpos/3d/success.phphttps://localhost/pos/examples/interpos/3d/fail.php20220327140D810',
                ],
            ],
        ];
    }


    public static function paymentTestDataProvider(): array
    {
        return [
            'fail1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'OrderId'               => '20221225662C',
                    'ProcReturnCode'        => '81',
                    'HostRefNum'            => null,
                    'AuthCode'              => '',
                    'TxnResult'             => 'Failed',
                    'ErrorMessage'          => 'Terminal Aktif Degil',
                    'CampanyId'             => '',
                    'CampanyInstallCount'   => '0',
                    'CampanyShiftDateCount' => '0',
                    'CampanyTxnId'          => '',
                    'CampanyType'           => '',
                    'CampanyInstallment'    => '0',
                    'CampanyDate'           => '0',
                    'CampanyAmnt'           => '0',
                    'TRXDATE'               => '',
                    'TransId'               => '',
                    'ErrorCode'             => 'B810002',
                    'EarnedBonus'           => '0',
                    'UsedBonus'             => '0',
                    'AvailableBonus'        => '0',
                    'BonusToBonus'          => '0',
                    'CampaignBonus'         => '0',
                    'FoldedBonus'           => '0',
                    'SurchargeAmount'       => '0',
                    'Amount'                => '1,01',
                    'CardHolderName'        => '',
                ],
                'expectedData' => [
                    'order_id'          => '20221225662C',
                    'transaction_id'    => null,
                    'transaction_type'  => 'pay',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'payment_model'     => 'regular',
                    'auth_code'         => null,
                    'ref_ret_num'       => null,
                    'batch_num'         => null,
                    'proc_return_code'  => '81',
                    'status'            => 'declined',
                    'status_detail'     => 'invalid_credentials',
                    'error_code'        => 'B810002',
                    'error_message'     => 'Terminal Aktif Degil',
                    'installment_count' => null,
                    'transaction_time'  => null,
                ],
            ],
            'empty' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [],
                'expectedData' => [
                    'order_id'          => null,
                    'transaction_id'    => null,
                    'transaction_time'  => null,
                    'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                    'installment_count' => null,
                    'currency'          => null,
                    'amount'            => null,
                    'payment_model'     => 'regular',
                    'auth_code'         => null,
                    'ref_ret_num'       => null,
                    'batch_num'         => null,
                    'proc_return_code'  => null,
                    'status'            => 'declined',
                    'status_detail'     => null,
                    'error_code'        => null,
                    'error_message'     => null,
                ],
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'authFail1' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'Version'                 => null,
                    'MerchantID'              => null,
                    'ShopCode'                => '3123',
                    'TxnStat'                 => 'N',
                    'MD'                      => null,
                    'RetCode'                 => null,
                    'RetDet'                  => null,
                    'VenderCode'              => null,
                    'Eci'                     => null,
                    'PayerAuthenticationCode' => null,
                    'PayerTxnId'              => null,
                    'CavvAlg'                 => null,
                    'PAResVerified'           => 'False',
                    'PAResSyntaxOK'           => 'False',
                    'Expiry'                  => '****',
                    'Pan'                     => '540061******0430',
                    'OrderId'                 => '20221225E1DF',
                    'PurchAmount'             => '1,01',
                    'Exponent'                => null,
                    'Description'             => null,
                    'Description2'            => null,
                    'Currency'                => '949',
                    'OkUrl'                   => 'http:\/\/localhost\/interpos\/3d\/response.php',
                    'FailUrl'                 => 'http:\/\/localhost\/interpos\/3d\/response.php',
                    '3DStatus'                => '0',
                    'AuthCode'                => null,
                    'HostRefNum'              => null,
                    'TransId'                 => null,
                    'TRXDATE'                 => null,
                    'CardHolderName'          => null,
                    'mdStatus'                => '0',
                    'ProcReturnCode'          => '81',
                    'TxnResult'               => null,
                    'ErrorMessage'            => 'Terminal Aktif Degil',
                    'ErrorCode'               => 'B810002',
                    'Response'                => null,
                    'HASH'                    => '423AWRAXl0VlEbQjpmAfntT5e3E=',
                    'HASHPARAMS'              => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'           => '1,01949http:\/\/localhost\/interpos\/3d\/response.phphttp:\/\/localhost\/interpos\/3d\/response.php20221225E1DF810',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id'             => '20221225E1DF',
                    'transaction_id'       => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '81',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_credentials',
                    'error_code'           => 'B810002',
                    'error_message'        => 'Terminal Aktif Degil',
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '540061******0430',
                    'month'                => null,
                    'year'                 => null,
                    'amount'               => 1.01,
                    'currency'             => 'TRY',
                    'eci'                  => null,
                    'tx_status'            => 'N',
                    'cavv'                 => null,
                    'md_error_message'     => 'Terminal Aktif Degil',
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                    'transaction_time'     => null,
                ],
            ],
            'success'   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'Version'                 => '',
                    'MerchantID'              => '',
                    'ShopCode'                => 'gizlendi',
                    'TxnStat'                 => 'Y',
                    'MD'                      => 'gizlendi',
                    'RetCode'                 => '',
                    'RetDet'                  => '',
                    'VenderCode'              => '',
                    'Eci'                     => '02',
                    'PayerAuthenticationCode' => 'gizlendi=',
                    'PayerTxnId'              => '',
                    'CavvAlg'                 => '',
                    'PAResVerified'           => 'True',
                    'PAResSyntaxOK'           => 'True',
                    'Expiry'                  => 'expiry-123',
                    'Pan'                     => 'kart*****no',
                    'OrderId'                 => '33554969',
                    'PurchAmount'             => '1',
                    'Exponent'                => '',
                    'Description'             => '',
                    'Description2'            => '',
                    'Currency'                => '949',
                    'OkUrl'                   => 'gizlendi',
                    'FailUrl'                 => 'gizlendi',
                    '3DStatus'                => '1',
                    'AuthCode'                => '',
                    'HostRefNum'              => null,
                    'TransId'                 => '',
                    'TRXDATE'                 => '',
                    'CardHolderName'          => '',
                    'mdStatus'                => '1',
                    'ProcReturnCode'          => '',
                    'TxnResult'               => '',
                    'ErrorMessage'            => '',
                    'ErrorCode'               => '',
                    'Response'                => '',
                    'HASH'                    => 'gizlendi/gizlendi=',
                    'HASHPARAMS'              => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'           => 'gizlendi',
                ],
                'paymentData'        => [
                    'OrderId'                       => '33554969',
                    'ProcReturnCode'                => '00',
                    'HostRefNum'                    => null,
                    'AuthCode'                      => 'auth-code-123',
                    'TxnResult'                     => 'Success',
                    'ErrorMessage'                  => '',
                    'CampanyId'                     => '',
                    'CampanyInstallCount'           => '0',
                    'CampanyShiftDateCount'         => '0',
                    'CampanyTxnId'                  => '',
                    'CampanyType'                   => '',
                    'CampanyInstallment'            => '0',
                    'CampanyDate'                   => '0',
                    'CampanyAmnt'                   => '0',
                    'TRXDATE'                       => '09.08.2024 10:40:34',
                    'TransId'                       => 'trans-id-123',
                    'ErrorCode'                     => '',
                    'EarnedBonus'                   => '0,00',
                    'UsedBonus'                     => '0,00',
                    'AvailableBonus'                => '0,00',
                    'BonusToBonus'                  => '0',
                    'CampaignBonus'                 => '0,00',
                    'FoldedBonus'                   => '0',
                    'SurchargeAmount'               => '0',
                    'Amount'                        => '1,00',
                    'CardHolderName'                => 'kart-sahibi-abc',
                    'QrReferenceNumber'             => '',
                    'QrCardToken'                   => '',
                    'QrData'                        => '',
                    'QrPayIsSucess'                 => 'False',
                    'QrIssuerPaymentMethod'         => '',
                    'QrFastMessageReferenceNo'      => '',
                    'QrFastParticipantReceiverCode' => '',
                    'QrFastParticipantReceiverName' => '',
                    'QrFastParticipantSenderCode'   => '',
                    'QrFastSenderIban'              => '',
                    'QrFastParticipantSenderName'   => '',
                    'QrFastPaymentResultDesc'       => '',
                ],
                'expectedData'       => [
                    'order_id'             => '33554969',
                    'transaction_id'       => 'trans-id-123',
                    'auth_code'            => 'auth-code-123',
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'transaction_security' => 'Full 3D Secure',
                    'md_status'            => '1',
                    'masked_number'        => 'kart*****no',
                    'month'                => null,
                    'year'                 => null,
                    'amount'               => 1.0,
                    'currency'             => 'TRY',
                    'eci'                  => '02',
                    'tx_status'            => 'Y',
                    'cavv'                 => null,
                    'md_error_message'     => null,
                    'transaction_type'     => 'pay',
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                    'transaction_time'     => new \DateTimeImmutable('09.08.2024 10:40:34'),
                ],
            ],
        ];
    }


    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'authFail1' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'Version'                 => '',
                    'MerchantID'              => '',
                    'ShopCode'                => '3123',
                    'TxnStat'                 => 'N',
                    'MD'                      => '',
                    'RetCode'                 => '',
                    'RetDet'                  => '',
                    'VenderCode'              => '',
                    'Eci'                     => '',
                    'PayerAuthenticationCode' => '',
                    'PayerTxnId'              => '',
                    'CavvAlg'                 => '',
                    'PAResVerified'           => 'False',
                    'PAResSyntaxOK'           => 'False',
                    'Expiry'                  => '****',
                    'Pan'                     => '540061******0430',
                    'OrderId'                 => '20221225B83B',
                    'PurchAmount'             => '1,01',
                    'Exponent'                => '',
                    'Description'             => '',
                    'Description2'            => '',
                    'Currency'                => '949',
                    'OkUrl'                   => 'http:\/\/localhost\/interpos\/3d-pay\/response.php',
                    'FailUrl'                 => 'http:\/\/localhost\/interpos\/3d-pay\/response.php',
                    '3DStatus'                => '0',
                    'AuthCode'                => '',
                    'HostRefNum'              => null,
                    'TransId'                 => '',
                    'TRXDATE'                 => '',
                    'CardHolderName'          => '',
                    'mdStatus'                => '0',
                    'ProcReturnCode'          => '81',
                    'TxnResult'               => '',
                    'ErrorMessage'            => 'Terminal Aktif Degil',
                    'ErrorCode'               => 'B810002',
                    'Response'                => '',
                    'HASH'                    => 'PvDXe6Puf9W2oZnBZuHVp8oWpyY=',
                    'HASHPARAMS'              => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'           => '1,01949http:\/\/localhost\/interpos\/3d-pay\/response.phphttp:\/\/localhost\/interpos\/3d-pay\/response.php20221225B83B810',
                ],
                'expectedData' => [
                    'order_id'             => '20221225B83B',
                    'transaction_id'       => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '81',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_credentials',
                    'error_code'           => 'B810002',
                    'error_message'        => 'Terminal Aktif Degil',
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '540061******0430',
                    'month'                => null,
                    'year'                 => null,
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => null,
                    'tx_status'            => 'N',
                    'cavv'                 => null,
                    'md_error_message'     => 'Terminal Aktif Degil',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'payment_model'        => '3d_pay',
                    'installment_count'    => null,
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            '3d_auth_fail1' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentData'  => [
                    'Version'                 => '',
                    'MerchantID'              => '',
                    'ShopCode'                => '3123',
                    'TxnStat'                 => 'N',
                    'MD'                      => '',
                    'RetCode'                 => '',
                    'RetDet'                  => '',
                    'VenderCode'              => '',
                    'Eci'                     => '',
                    'PayerAuthenticationCode' => '',
                    'PayerTxnId'              => '',
                    'CavvAlg'                 => '',
                    'PAResVerified'           => 'False',
                    'PAResSyntaxOK'           => 'False',
                    'Expiry'                  => '****',
                    'Pan'                     => '540061******0430',
                    'OrderId'                 => '202212256D26',
                    'PurchAmount'             => '1,01',
                    'Exponent'                => '',
                    'Description'             => '',
                    'Description2'            => '',
                    'Currency'                => '949',
                    'OkUrl'                   => 'http:\/\/localhost\/interpos\/3d-host\/response.php',
                    'FailUrl'                 => 'http:\/\/localhost\/interpos\/3d-host\/response.php',
                    '3DStatus'                => '0',
                    'AuthCode'                => '',
                    'HostRefNum'              => null,
                    'TransId'                 => '',
                    'TRXDATE'                 => '',
                    'CardHolderName'          => '',
                    'mdStatus'                => '0',
                    'ProcReturnCode'          => '81',
                    'TxnResult'               => '',
                    'ErrorMessage'            => 'Terminal Aktif Degil',
                    'ErrorCode'               => 'B810002',
                    'Response'                => '',
                    'HASH'                    => 'hmL3n1OMlNnKM4mjk2BgqfFM0rI=',
                    'HASHPARAMS'              => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'           => '1,01949http:\/\/localhost\/interpos\/3d-host\/response.phphttp:\/\/localhost\/interpos\/3d-host\/response.php202212256D26810',
                    '__EVENTTARGET'           => '',
                    '__EVENTARGUMENT'         => '',
                ],
                'expectedData' => [
                    'order_id'             => '202212256D26',
                    'transaction_id'       => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '81',
                    'status'               => 'declined',
                    'status_detail'        => 'invalid_credentials',
                    'error_code'           => 'B810002',
                    'error_message'        => 'Terminal Aktif Degil',
                    'transaction_security' => 'MPI fallback',
                    'md_status'            => '0',
                    'masked_number'        => '540061******0430',
                    'month'                => null,
                    'year'                 => null,
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'eci'                  => null,
                    'tx_status'            => 'N',
                    'cavv'                 => null,
                    'md_error_message'     => 'Terminal Aktif Degil',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'payment_model'        => '3d_host',
                    'installment_count'    => null,
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): array
    {
        return [
            'fail1' => [
                'responseData' => [
                    'OrderId'        => 'SYSOID121327781',
                    'ProcReturnCode' => '81',
                    'BatchNo'        => '',
                    'TransId'        => '',
                    'TRXDATE'        => '',
                    'TxnStat'        => '',
                    'PurchAmount'    => '0',
                    'VoidDate'       => '1.1.0001 00:00:00',
                    'TxnStatus'      => '',
                    'ChargeTypeCd'   => '',
                    'ErrorCode'      => 'B810002',
                    'ErrorMessage'   => 'TR:Terminal Aktif Degil',
                    'RefundedAmount' => '0',
                    'AuthCode'       => '',
                ],
                'expectedData' => [
                    'order_id'          => 'SYSOID121327781',
                    'auth_code'         => null,
                    'proc_return_code'  => '81',
                    'transaction_id'    => null,
                    'error_code'        => '81',
                    'error_message'     => 'TR:Terminal Aktif Degil',
                    'ref_ret_num'       => null,
                    'order_status'      => null,
                    'transaction_type'  => null,
                    'currency'          => null,
                    'masked_number'     => null,
                    'refund_amount'     => null,
                    'capture_amount'    => null,
                    'first_amount'      => null,
                    'status'            => 'declined',
                    'status_detail'     => 'invalid_credentials',
                    'capture'           => null,
                    'transaction_time'  => null,
                    'capture_time'      => null,
                    'cancel_time'       => null,
                    'refund_time'       => null,
                    'installment_count' => null,
                ],
            ],
        ];
    }

    public static function cancelTestDataProvider(): array
    {
        return [
            'fail1' => [
                'responseData' => [
                    'OrderId'               => 'SYSOID121330755',
                    'ProcReturnCode'        => '81',
                    'HostRefNum'            => null,
                    'AuthCode'              => '',
                    'TxnResult'             => 'Failed',
                    'ErrorMessage'          => 'Terminal Aktif Degil',
                    'CampanyId'             => '',
                    'CampanyInstallCount'   => '0',
                    'CampanyShiftDateCount' => '0',
                    'CampanyTxnId'          => '',
                    'CampanyType'           => '',
                    'CampanyInstallment'    => '0',
                    'CampanyDate'           => '0',
                    'CampanyAmnt'           => '0',
                    'TRXDATE'               => '',
                    'TransId'               => '',
                    'ErrorCode'             => 'B810002',
                    'EarnedBonus'           => '0',
                    'UsedBonus'             => '0',
                    'AvailableBonus'        => '0',
                    'BonusToBonus'          => '0',
                    'CampaignBonus'         => '0',
                    'FoldedBonus'           => '0',
                    'SurchargeAmount'       => '0',
                    'Amount'                => '0',
                    'CardHolderName'        => '',
                ],
                'expectedData' => [
                    'order_id'         => 'SYSOID121330755',
                    'group_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => '81',
                    'transaction_id'   => null,
                    'error_code'       => 'B810002',
                    'error_message'    => 'Terminal Aktif Degil',
                    'status'           => 'declined',
                    'status_detail'    => 'invalid_credentials',
                ],
            ],
        ];
    }

    public static function refundTestDataProvider(): array
    {
        return [
            'fail1' => [
                'responseData' => [
                    'OrderId'               => 'SYSOID121332551',
                    'ProcReturnCode'        => '81',
                    'HostRefNum'            => null,
                    'AuthCode'              => '',
                    'TxnResult'             => 'Failed',
                    'ErrorMessage'          => 'Terminal Aktif Degil',
                    'CampanyId'             => '',
                    'CampanyInstallCount'   => '0',
                    'CampanyShiftDateCount' => '0',
                    'CampanyTxnId'          => '',
                    'CampanyType'           => '',
                    'CampanyInstallment'    => '0',
                    'CampanyDate'           => '0',
                    'CampanyAmnt'           => '0',
                    'TRXDATE'               => '',
                    'TransId'               => '',
                    'ErrorCode'             => 'B810002',
                    'EarnedBonus'           => '0',
                    'UsedBonus'             => '0',
                    'AvailableBonus'        => '0',
                    'BonusToBonus'          => '0',
                    'CampaignBonus'         => '0',
                    'FoldedBonus'           => '0',
                    'SurchargeAmount'       => '0',
                    'Amount'                => '1,01',
                    'CardHolderName'        => '',
                ],
                'expectedData' => [
                    'order_id'         => 'SYSOID121332551',
                    'group_id'         => null,
                    'auth_code'        => null,
                    'ref_ret_num'      => null,
                    'proc_return_code' => '81',
                    'transaction_id'   => null,
                    'error_code'       => 'B810002',
                    'error_message'    => 'Terminal Aktif Degil',
                    'status'           => 'declined',
                    'status_detail'    => 'invalid_credentials',
                ],
            ],
        ];
    }
}
