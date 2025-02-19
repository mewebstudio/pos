<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class PayForPosResponseDataMapperTest extends TestCase
{
    private PayForPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var ResponseValueFormatterInterface & MockObject */
    private ResponseValueFormatterInterface $responseValueFormatter;

    /** @var ResponseValueMapperInterface & MockObject */
    private ResponseValueMapperInterface $responseValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->responseValueFormatter = $this->createMock(ResponseValueFormatterInterface::class);
        $this->responseValueMapper    = $this->createMock(ResponseValueMapperInterface::class);

        $this->responseDataMapper = new PayForPosResponseDataMapper(
            $this->responseValueFormatter,
            $this->responseValueMapper,
            $this->logger
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
        if (isset($expectedData['transaction_time'])) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatDateTime')
                ->with('now', $txType)
                ->willReturn($expectedData['transaction_time']);
        }

        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, $order);

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
        $this->responseValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($threeDResponseData['TxnType'])
            ->willReturn($expectedData['transaction_type']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapSecureType')
            ->with($threeDResponseData['SecureType'], $txType)
            ->willReturn($expectedData['payment_model']);

        $this->responseValueFormatter->expects($this->once())
            ->method('formatAmount')
            ->with($threeDResponseData['PurchAmount'], $txType)
            ->willReturn($expectedData['amount']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapCurrency')
            ->with($threeDResponseData['Currency'], $txType)
            ->willReturn($expectedData['currency']);

        if ($expectedData['status'] === $this->responseDataMapper::TX_APPROVED) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatDateTime')
                ->with($threeDResponseData['TransactionDate'], $txType)
                ->willReturn($expectedData['transaction_time']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($threeDResponseData['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);
        }

        $actualData = $this->responseDataMapper->map3DPaymentData(
            $threeDResponseData,
            $paymentResponse,
            $txType,
            $order
        );

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
        $this->responseValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($responseData['TxnType'])
            ->willReturn($expectedData['transaction_type']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapSecureType')
            ->with($responseData['SecureType'], $txType)
            ->willReturn($expectedData['payment_model']);

        $this->responseValueFormatter->expects($this->once())
            ->method('formatAmount')
            ->with($responseData['PurchAmount'], $txType)
            ->willReturn($expectedData['amount']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapCurrency')
            ->with($responseData['Currency'], $txType)
            ->willReturn($expectedData['currency']);

        if ($expectedData['status'] === $this->responseDataMapper::TX_APPROVED) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatDateTime')
                ->with($responseData['TransactionDate'], $txType)
                ->willReturn($expectedData['transaction_time']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($responseData['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);
        }

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
        $this->responseValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($responseData['TxnType'])
            ->willReturn($expectedData['transaction_type']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapSecureType')
            ->with($responseData['SecureType'], $txType)
            ->willReturn($expectedData['payment_model']);

        $this->responseValueFormatter->expects($this->once())
            ->method('formatAmount')
            ->with($responseData['PurchAmount'], $txType)
            ->willReturn($expectedData['amount']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapCurrency')
            ->with($responseData['Currency'], $txType)
            ->willReturn($expectedData['currency']);

        if ($expectedData['status'] === $this->responseDataMapper::TX_APPROVED) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatDateTime')
                ->with($responseData['TransactionDate'], $txType)
                ->willReturn($expectedData['transaction_time']);

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($responseData['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);
        }

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
        $txType = PosInterface::TX_TYPE_STATUS;
        $this->responseValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($responseData['TxnType'])
            ->willReturn($expectedData['transaction_type']);

        $this->responseValueMapper->expects($this->once())
            ->method('mapCurrency')
            ->with($responseData['Currency'], $txType)
            ->willReturn($expectedData['currency']);

        if ($expectedData['status'] === $this->responseDataMapper::TX_APPROVED) {
            $this->responseValueFormatter->expects($this->once())
                ->method('formatAmount')
                ->with($responseData['PurchAmount'], $txType)
                ->willReturn($expectedData['first_amount']);

            $dateTimeMatcher = $this->atLeastOnce();
            $this->responseValueFormatter->expects($dateTimeMatcher)
                ->method('formatDateTime')
                ->with($this->callback(function ($dateTime) use ($dateTimeMatcher, $responseData) {
                    if ($dateTimeMatcher->getInvocationCount() === 1) {
                        return $dateTime === $responseData['InsertDatetime'];
                    }
                    if ($responseData['VoidDate'] > 0) {
                        return $dateTime === $responseData['VoidDate'].'T'.$responseData['VoidTime'];
                    }

                    return false;
                }), $txType)
                ->willReturnCallback(
                    function () use ($dateTimeMatcher, $expectedData) {
                        if ($dateTimeMatcher->getInvocationCount() === 1) {
                            return $expectedData['transaction_time'];
                        }
                        if ($dateTimeMatcher->getInvocationCount() === 2) {
                            return $expectedData['cancel_time'];
                        }

                        return false;
                    }
                );

            $this->responseValueFormatter->expects($this->once())
                ->method('formatInstallment')
                ->with($responseData['InstallmentCount'], $txType)
                ->willReturn($expectedData['installment_count']);
        }

        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * Doing integration test because of the iteration, sorting and conditional statements it is difficult to mock values.
     * @dataProvider orderHistoryTestDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $requestValueMapper = RequestValueMapperFactory::createForGateway(PayForPos::class);
        $responseDataMapper = new PayForPosResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(PayForPos::class),
            ResponseValueMapperFactory::createForGateway(PayForPos::class, $requestValueMapper),
            $this->logger
        );
        $actualData = $responseDataMapper->mapOrderHistoryResponse($responseData);

        if (count($actualData['transactions']) > 1
            && null !== $actualData['transactions'][0]['transaction_time']
            && null !== $actualData['transactions'][1]['transaction_time']
        ) {
            $this->assertGreaterThan(
                $actualData['transactions'][0]['transaction_time'],
                $actualData['transactions'][1]['transaction_time'],
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
        unset($actualData['all']);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider historyTestDataProvider
     */
    public function testMapHistoryResponse(array $responseData, array $expectedData): void
    {
        $requestValueMapper = RequestValueMapperFactory::createForGateway(PayForPos::class);
        $responseDataMapper = new PayForPosResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(PayForPos::class),
            ResponseValueMapperFactory::createForGateway(PayForPos::class, $requestValueMapper),
            $this->logger
        );
        $actualData = $responseDataMapper->mapHistoryResponse($responseData);

        if (count($actualData['transactions']) > 1
            && null !== $actualData['transactions'][0]['transaction_time']
            && null !== $actualData['transactions'][1]['transaction_time']
        ) {
            $this->assertGreaterThan(
                $actualData['transactions'][0]['transaction_time'],
                $actualData['transactions'][1]['transaction_time'],
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
            'success1'        => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'AuthCode'       => 'S77788',
                    'HostRefNum'     => '230422096719',
                    'ProcReturnCode' => '00',
                    'TransId'        => '202210313C0D',
                    'ErrMsg'         => 'Onaylandı',
                    'CardHolderName' => 'John Doe',
                ],
                'expectedData' => [
                    'transaction_id'    => '202210313C0D',
                    'transaction_type'  => 'pay',
                    'transaction_time'  => new \DateTimeImmutable(),
                    'payment_model'     => 'regular',
                    'order_id'          => '202210313C0D',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'auth_code'         => 'S77788',
                    'ref_ret_num'       => '230422096719',
                    'batch_num'         => null,
                    'proc_return_code'  => '00',
                    'status'            => 'approved',
                    'status_detail'     => 'approved',
                    'error_code'        => null,
                    'error_message'     => null,
                    'installment_count' => null,
                ],
            ],
            'fail1'           => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'AuthCode'       => '',
                    'HostRefNum'     => '230422097442',
                    'ProcReturnCode' => 'M041',
                    'TransId'        => '2022103155EF',
                    'ErrMsg'         => 'Geçersiz kart numarası',
                    'CardHolderName' => 'John Doe',
                ],
                'expectedData' => [
                    'transaction_id'    => '2022103155EF',
                    'transaction_type'  => 'pay',
                    'transaction_time'  => null,
                    'payment_model'     => 'regular',
                    'order_id'          => '2022103155EF',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'auth_code'         => null,
                    'ref_ret_num'       => '230422097442',
                    'batch_num'         => null,
                    'proc_return_code'  => 'M041',
                    'status'            => 'declined',
                    'status_detail'     => 'reject',
                    'error_code'        => 'M041',
                    'error_message'     => 'Geçersiz kart numarası',
                    'installment_count' => null,
                ],
            ],
            'post_auth_fail1' => [
                'order'        => [
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 1.01,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'responseData' => [
                    'AuthCode'       => '',
                    'HostRefNum'     => '230422097825',
                    'ProcReturnCode' => 'V013',
                    'TransId'        => '20221031F9FA2',
                    'ErrMsg'         => 'Seçili İşlem Bulunamadı!',
                    'CardHolderName' => '',
                ],
                'expectedData' => [
                    'transaction_id'    => '20221031F9FA2',
                    'transaction_type'  => 'post',
                    'transaction_time'  => null,
                    'payment_model'     => 'regular',
                    'order_id'          => '20221031F9FA2',
                    'currency'          => 'TRY',
                    'amount'            => 1.01,
                    'auth_code'         => null,
                    'ref_ret_num'       => '230422097825',
                    'batch_num'         => null,
                    'proc_return_code'  => 'V013',
                    'status'            => 'declined',
                    'status_detail'     => 'reject',
                    'error_code'        => 'V013',
                    'error_message'     => 'Seçili İşlem Bulunamadı!',
                    'installment_count' => null,
                ],
            ],
        ];
    }


    public static function threeDPaymentDataProvider(): array
    {
        return [
            'auth_fail1'                 => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'threeDResponseData' => [
                    'RequestGuid'                    => '1000000081255934',
                    'TransactionDate'                => '31.10.2022 22:39:44',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '202210317565',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,4,5',
                    'SecureType'                     => '3DModel',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMjM5NDI2MzgwMjg=',
                    'PayerAuthenticationCode'        => '',
                    'Eci'                            => '',
                    'MD'                             => '',
                    'Hash'                           => 'Y56v0yOrT+l4fZoRh2wP+nACbwg=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '0',
                    'Rnd'                            => '97a44150e30c0c202978e7328a5258a3',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'John Doe',
                    'IrcDet'                         => '3D Secure Authorize Error',
                    'IrcCode'                        => 'MR15',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'N',
                    'CavvAlg'                        => '',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'false',
                    'ErrMsg'                         => '3D Kullanıcı Doğrulama Adımı Başarısız',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'N',
                    '3DStatus'                       => '-1',
                    'TxnResult'                      => 'Failed',
                    'AuthCode'                       => '',
                    'HostRefNum'                     => '',
                    'ProcReturnCode'                 => 'V034',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26783433',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '98691',
                    'F37'                            => '',
                    'RRN'                            => '',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '0',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '2',
                    'MpiElapsedTime'                 => '0',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => '',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028527847983529',
                    'ResponseHash'                   => 'Jzj512Gluz+bmMD8sQYrqCuAQ30=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id'             => '202210317565',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => 'V034',
                    'status'               => 'declined',
                    'status_detail'        => 'try_again',
                    'error_code'           => 'V034',
                    'error_message'        => '3D Kullanıcı Doğrulama Adımı Başarısız',
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Failed',
                    'md_status'            => '-1',
                    'md_error_code'        => 'V034',
                    'md_error_message'     => '3D Kullanıcı Doğrulama Adımı Başarısız',
                    'md_status_detail'     => 'try_again',
                    'eci'                  => null,
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            'order_number_already_exist' => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'threeDResponseData' => [
                    'RequestGuid'                    => '0',
                    'TransactionDate'                => '17.03.2024 17:47:28',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '202403173F72',
                    'RequestIp'                      => '88.152.8.2',
                    'RequestStat'                    => '1,5',
                    'SecureType'                     => '3DModel',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d/response.php',
                    'PayerTxnId'                     => '',
                    'PayerAuthenticationCode'        => '',
                    'Eci'                            => '',
                    'MD'                             => '',
                    'Hash'                           => 'HzDLP/OkUrpRiMXc6udJsmOddxA=',
                    'TerminalID'                     => '',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => '',
                    'Lang'                           => 'TR',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '0',
                    'Rnd'                            => 'db19313ab96b715bbfa06e6',
                    'AlphaCode'                      => '',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '',
                    'MrcName'                        => '',
                    'MerchantHomeUrl'                => '',
                    'CardHolderName'                 => 'John Doe',
                    'IrcDet'                         => '',
                    'IrcCode'                        => '',
                    'Version'                        => '',
                    'TxnStatus'                      => '',
                    'CavvAlg'                        => '',
                    'ParesVerified'                  => '',
                    'ParesSyntaxOk'                  => '',
                    'ErrMsg'                         => 'Verilen sipariş no önceden kullanılmıştır.',
                    'VendorDet'                      => '',
                    'D3Stat'                         => '',
                    '3DStatus'                       => '-1',
                    'TxnResult'                      => '',
                    'AuthCode'                       => '',
                    'HostRefNum'                     => '',
                    'ProcReturnCode'                 => '101310',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '0',
                    'VoidDate'                       => '',
                    'CardMask'                       => '',
                    'ReqId'                          => '0',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '0',
                    'SysDate'                        => '0',
                    'F11'                            => '0',
                    'F37'                            => '',
                    'RRN'                            => '',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '0',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '0',
                    'MpiElapsedTime'                 => '0',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => '',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => '',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '88.152.8.2',
                    'ResponseRnd'                    => 'PF638462944484307452',
                    'ResponseHash'                   => 'wWLUtYZD9VUIi9Pl3mAyS02LLsUxWy6lauMfFBiuVDw=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'ArtiTaksit'                     => '0',
                    'AuthId'                         => '',
                ],
                'paymentData'        => [],
                'expectedData'       => [
                    'order_id'             => '202403173F72',
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => '101310',
                    'status'               => 'declined',
                    'status_detail'        => null,
                    'error_code'           => '101310',
                    'error_message'        => 'Verilen sipariş no önceden kullanılmıştır.',
                    'masked_number'        => null,
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => null,
                    'md_status'            => '-1',
                    'md_error_code'        => '101310',
                    'md_error_message'     => 'Verilen sipariş no önceden kullanılmıştır.',
                    'md_status_detail'     => null,
                    'eci'                  => null,
                    'payment_model'        => '3d',
                    'installment_count'    => null,
                ],
            ],
            'success1'                   => [
                'order'              => [],
                'txType'             => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'threeDResponseData' => [
                    'RequestGuid'                    => '1000000081255931',
                    'TransactionDate'                => '31.10.2022 22:34:18',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '20221031CFD0',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,3,5',
                    'SecureType'                     => '3DModel',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMjM0MTM2MzgwMjg=',
                    'PayerAuthenticationCode'        => 'AAABBCaIaIAoUkWEBYhoAAAAAAA=',
                    'Eci'                            => '05',
                    'MD'                             => '',
                    'Hash'                           => 'KuJcFyXYZcW8NetghUN7uBgzQPE=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '0',
                    'Rnd'                            => '94381aefb4b8865c54cce0a1f1c74f12',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'John Doe',
                    'IrcDet'                         => '',
                    'IrcCode'                        => '',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'N',
                    'CavvAlg'                        => '2',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'true',
                    'ErrMsg'                         => '3D Kullanıcı Doğrulama Adımı Başarılı',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'Y',
                    '3DStatus'                       => '1',
                    'TxnResult'                      => 'Failed',
                    'AuthCode'                       => '',
                    'HostRefNum'                     => '',
                    'ProcReturnCode'                 => 'V033',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26782891',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '98249',
                    'F37'                            => '',
                    'RRN'                            => '',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '0',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '2',
                    'MpiElapsedTime'                 => '0',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => '',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028524588902912',
                    'ResponseHash'                   => '6OWhNzqTEXUGkY6HN90QKHdP53c=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'paymentData'        => [
                    'AuthCode'       => 'S37397',
                    'HostRefNum'     => '230422098249',
                    'ProcReturnCode' => '00',
                    'TransId'        => '20221031CFD0',
                    'ErrMsg'         => 'Onaylandı',
                    'CardHolderName' => 'John Doe',
                ],
                'expectedData'       => [
                    'transaction_id'       => '20221031CFD0',
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-10-31 22:34:18'),
                    'transaction_security' => null,
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Failed',
                    'md_status'            => '1',
                    'md_error_code'        => null,
                    'md_error_message'     => null,
                    'md_status_detail'     => null,
                    'eci'                  => '05',
                    'auth_code'            => 'S37397',
                    'ref_ret_num'          => '230422098249',
                    'batch_num'            => '4320',
                    'order_id'             => '20221031CFD0',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'payment_model'        => '3d',
                    'installment_count'    => 0,
                ],
            ],
        ];
    }


    public static function threeDPayPaymentDataProvider(): array
    {
        return [
            'success1'   => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentData'  => [
                    'RequestGuid'                    => '1000000081255944',
                    'TransactionDate'                => '31.10.2022 22:56:43',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '2022103114B3',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,3,10,5',
                    'SecureType'                     => '3DPay',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMjU2Mzg2MzgwMjg=',
                    'PayerAuthenticationCode'        => 'AAABASI0Q4AoU4AomDRDAAAAAAA=',
                    'Eci'                            => '05',
                    'MD'                             => '',
                    'Hash'                           => 'lzsn2UeZpuOabSYTwbVfixk0tbg=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '2',
                    'Rnd'                            => 'aff83bea26a1d13a0976f9cb6c14ee27',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'John Doe',
                    'IrcDet'                         => '',
                    'IrcCode'                        => '',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'Y',
                    'CavvAlg'                        => '2',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'true',
                    'ErrMsg'                         => 'Onaylandı',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'Y',
                    '3DStatus'                       => '1',
                    'TxnResult'                      => 'Success',
                    'AuthCode'                       => 'S86797',
                    'HostRefNum'                     => '230422100150',
                    'ProcReturnCode'                 => '00',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26784792',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '100150',
                    'F37'                            => '230422100150',
                    'RRN'                            => '230422100150',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '254',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '4',
                    'MpiElapsedTime'                 => '4977',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => 'TR',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028538035077529',
                    'ResponseHash'                   => 'il/SWNG2llQkLgnCbUG6lPYtlgM=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-10-31 22:56:43'),
                    'transaction_security' => null,
                    'auth_code'            => 'S86797',
                    'ref_ret_num'          => '230422100150',
                    'batch_num'            => '4320',
                    'order_id'             => '2022103114B3',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Success',
                    'md_status'            => '1',
                    'md_error_code'        => null,
                    'md_error_message'     => null,
                    'md_status_detail'     => 'approved',
                    'eci'                  => '05',
                    'installment_count'    => 2,
                    'payment_model'        => '3d_pay',
                ],
            ],
            'auth_fail1' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentData'  => [
                    'RequestGuid'                    => '1000000081255948',
                    'TransactionDate'                => '31.10.2022 23:01:36',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '202210317223',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,4,5',
                    'SecureType'                     => '3DPay',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMzAxMzI2MzgwMjg=',
                    'PayerAuthenticationCode'        => '',
                    'Eci'                            => '',
                    'MD'                             => '',
                    'Hash'                           => 'VbadsdpyAqh56fzNOC3ewhFXrHY=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '0',
                    'Rnd'                            => '206e224bf1ddc63edc7c5f5b519f9f0a',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'John Doe',
                    'IrcDet'                         => '3D Secure Authorize Error',
                    'IrcCode'                        => 'MR15',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'N',
                    'CavvAlg'                        => '',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'false',
                    'ErrMsg'                         => '3D Secure Authorize Error',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'N',
                    '3DStatus'                       => '-1',
                    'TxnResult'                      => 'Failed',
                    'AuthCode'                       => '',
                    'HostRefNum'                     => '',
                    'ProcReturnCode'                 => 'MR15',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d-pay/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26785209',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '100567',
                    'F37'                            => '',
                    'RRN'                            => '',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '0',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '2',
                    'MpiElapsedTime'                 => '0',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => '',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028540963074026',
                    'ResponseHash'                   => 'n3zpDnIyZcVRGVowGNuMK4rH3x4=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'order_id'             => '202210317223',
                    'proc_return_code'     => 'MR15',
                    'status'               => 'declined',
                    'status_detail'        => 'try_again',
                    'error_code'           => 'MR15',
                    'error_message'        => '3D Secure Authorize Error',
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Failed',
                    'md_status'            => '-1',
                    'md_error_code'        => 'MR15',
                    'md_error_message'     => '3D Secure Authorize Error',
                    'md_status_detail'     => 'try_again',
                    'eci'                  => null,
                    'installment_count'    => null,
                    'payment_model'        => '3d_pay',
                ],
            ],
        ];
    }


    public static function threeDHostPaymentDataProvider(): array
    {
        return [
            'success1'   => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentData'  => [
                    'RequestGuid'                    => '1000000081265956',
                    'TransactionDate'                => '31.10.2022 23:06:37',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '2022103121CA',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,2,3,10,5',
                    'SecureType'                     => '3DHost',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMzA2MzI2MzgwMjg=',
                    'PayerAuthenticationCode'        => 'AAABBFJnWIAoVDljkGdYAAAAAAA=',
                    'Eci'                            => '05',
                    'MD'                             => '',
                    'Hash'                           => '7FYgYx1LMSNT8DaZx4yc4GqSdDA=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '3',
                    'Rnd'                            => '9e7a88deaf70a148fd40551d69503ba1',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'JOHN DOE',
                    'IrcDet'                         => '',
                    'IrcCode'                        => '',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'Y',
                    'CavvAlg'                        => '2',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'true',
                    'ErrMsg'                         => 'Onaylandı',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'Y',
                    '3DStatus'                       => '1',
                    'TxnResult'                      => 'Success',
                    'AuthCode'                       => 'S28031',
                    'HostRefNum'                     => '230423100695',
                    'ProcReturnCode'                 => '00',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26785344',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '100695',
                    'F37'                            => '230423100695',
                    'RRN'                            => '230423100695',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '24256',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '4',
                    'MpiElapsedTime'                 => '4422',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => 'TR',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028543970517651',
                    'ResponseHash'                   => 'R3sOuVFWDP8nvu9mtVtvLxwlqow=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => new \DateTimeImmutable('2022-10-31 23:06:37'),
                    'transaction_security' => null,
                    'auth_code'            => 'S28031',
                    'ref_ret_num'          => '230423100695',
                    'batch_num'            => '4320',
                    'order_id'             => '2022103121CA',
                    'proc_return_code'     => '00',
                    'status'               => 'approved',
                    'status_detail'        => 'approved',
                    'error_code'           => null,
                    'error_message'        => null,
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Success',
                    'md_status'            => '1',
                    'md_error_code'        => null,
                    'md_error_message'     => null,
                    'md_status_detail'     => 'approved',
                    'eci'                  => '05',
                    'installment_count'    => 3,
                    'payment_model'        => '3d_host',
                ],
            ],
            'auth_fail1' => [
                'order'        => [],
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentData'  => [
                    'RequestGuid'                    => '1000000081265960',
                    'TransactionDate'                => '31.10.2022 23:10:47',
                    'MbrId'                          => '5',
                    'MerchantID'                     => '085300000009704',
                    'OrderId'                        => '202210316DBA',
                    'RequestIp'                      => '89.244.149.137',
                    'RequestStat'                    => '1,2,4,5',
                    'SecureType'                     => '3DHost',
                    'PurchAmount'                    => '1.01',
                    'Exponent'                       => '2',
                    'Currency'                       => '949',
                    'Description'                    => '',
                    'OkUrl'                          => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'FailUrl'                        => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'PayerTxnId'                     => 'MjAyMjEwMzEyMzEwNDU2MzgwMjg=',
                    'PayerAuthenticationCode'        => '',
                    'Eci'                            => '',
                    'MD'                             => '',
                    'Hash'                           => 'njYhnUb4hEFmsMav7Z+QqG+91/U=',
                    'TerminalID'                     => 'VS010481',
                    'TxnType'                        => 'Auth',
                    'OrgOrderId'                     => '',
                    'SubMerchantCode'                => '',
                    'recur_frequency'                => '',
                    'recur_expiry'                   => '',
                    'CardType'                       => 'V',
                    'Lang'                           => 'tr',
                    'BonusAmount'                    => '',
                    'InstallmentCount'               => '3',
                    'Rnd'                            => '5fc1f8b2b3fd0b28959548f0951587e7',
                    'AlphaCode'                      => 'TL',
                    'Ecommerce'                      => '1',
                    'MrcCountryCode'                 => '792',
                    'MrcName'                        => '3D PAY TEST ISYERI',
                    'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                    'CardHolderName'                 => 'JOHN DOE',
                    'IrcDet'                         => '3D Secure Authorize Error',
                    'IrcCode'                        => 'MR15',
                    'Version'                        => '1.0.2',
                    'TxnStatus'                      => 'P',
                    'CavvAlg'                        => '',
                    'ParesVerified'                  => 'true',
                    'ParesSyntaxOk'                  => 'false',
                    'ErrMsg'                         => '3D Secure Authorize Error',
                    'VendorDet'                      => '',
                    'D3Stat'                         => 'N',
                    '3DStatus'                       => '-1',
                    'TxnResult'                      => 'Failed',
                    'AuthCode'                       => '',
                    'HostRefNum'                     => '',
                    'ProcReturnCode'                 => 'MR15',
                    'ReturnUrl'                      => 'http://localhost/finansbank-payfor/3d-host/response.php',
                    'ErrorData'                      => '',
                    'BatchNo'                        => '4320',
                    'VoidDate'                       => '',
                    'CardMask'                       => '415565******6111',
                    'ReqId'                          => '26785969',
                    'UsedPoint'                      => '0',
                    'SrcType'                        => 'VPO',
                    'RefundedAmount'                 => '0',
                    'RefundedPoint'                  => '0',
                    'ReqDate'                        => '20221031',
                    'SysDate'                        => '20221031',
                    'F11'                            => '101327',
                    'F37'                            => '',
                    'RRN'                            => '',
                    'IsRepeatTxn'                    => '',
                    'CavvResult'                     => '',
                    'VposElapsedTime'                => '18767',
                    'BankingElapsedTime'             => '0',
                    'SocketElapsedTime'              => '0',
                    'HsmElapsedTime'                 => '2',
                    'MpiElapsedTime'                 => '0',
                    'hasOrderId'                     => 'False',
                    'TemplateType'                   => '0',
                    'HasAddressCount'                => 'False',
                    'IsPaymentFacilitator'           => 'False',
                    'MerchantCountryCode'            => '',
                    'OrgTxnType'                     => '',
                    'F11_ORG'                        => '0',
                    'F12_ORG'                        => '0',
                    'F13_ORG'                        => '',
                    'F22_ORG'                        => '0',
                    'F25_ORG'                        => '0',
                    'MTI_ORG'                        => '0',
                    'DsBrand'                        => 'V',
                    'IntervalType'                   => '0',
                    'IntervalDuration'               => '0',
                    'RepeatCount'                    => '0',
                    'CustomerCode'                   => '',
                    'RequestMerchantDomain'          => '',
                    'RequestClientIp'                => '89.244.149.137',
                    'ResponseRnd'                    => 'PF638028546475263865',
                    'ResponseHash'                   => '7wnXcENucbamQSSzh95kRA8nzN8=',
                    'BankInternalResponseCode'       => '',
                    'BankInternalResponseMessage'    => '',
                    'BankInternalResponseSubcode'    => '',
                    'BankInternalResponseSubmessage' => '',
                    'BayiKodu'                       => '',
                    'VoidTime'                       => '0',
                    'VoidUserCode'                   => '',
                    'PaymentLinkId'                  => '0',
                    'ClientId'                       => '',
                    'IsQRValid'                      => '',
                    'IsFastValid'                    => '',
                    'IsQR'                           => '',
                    'IsFast'                         => '',
                    'QRRefNo'                        => '',
                    'FASTGonderenKatilimciKodu'      => '',
                    'FASTAlanKatilimciKodu'          => '',
                    'FASTReferansNo'                 => '',
                    'FastGonderenIBAN'               => '',
                    'FASTGonderenAdi'                => '',
                    'MobileECI'                      => '',
                    'HubConnId'                      => '',
                    'WalletData'                     => '',
                    'Tds2dsTransId'                  => '',
                    'Is3DHost'                       => '',
                    'HashType'                       => 'Sha1',
                ],
                'expectedData' => [
                    'transaction_id'       => null,
                    'transaction_type'     => 'pay',
                    'transaction_time'     => null,
                    'transaction_security' => null,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'order_id'             => '202210316DBA',
                    'proc_return_code'     => 'MR15',
                    'status'               => 'declined',
                    'status_detail'        => 'try_again',
                    'error_code'           => 'MR15',
                    'error_message'        => '3D Secure Authorize Error',
                    'masked_number'        => '415565******6111',
                    'amount'               => 1.01,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'tx_status'            => 'Failed',
                    'md_status'            => '-1',
                    'md_error_code'        => 'MR15',
                    'md_error_message'     => '3D Secure Authorize Error',
                    'md_status_detail'     => 'try_again',
                    'eci'                  => null,
                    'installment_count'    => null,
                    'payment_model'        => '3d_host',
                ],
            ],
        ];
    }


    public static function statusTestDataProvider(): iterable
    {
        $success1CaptureTime = new \DateTimeImmutable('31.10.2022 23:13:21');

        yield 'success1' => [
            'responseData' => [
                'RequestGuid'                    => '1000000081265961',
                'InsertDatetime'                 => '31.10.2022 23:13:21',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '202210312A24',
                'RequestIp'                      => '89.244.149.137',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'NonSecure',
                'PurchAmount'                    => '1.01',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'Auth',
                'OrgOrderId'                     => '',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => 'V',
                'Lang'                           => 'tr',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '0',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => 'John Doe',
                'IrcDet'                         => '',
                'IrcCode'                        => '',
                'Version'                        => '',
                'TxnStatus'                      => 'Y',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Onaylandı',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => 'Success',
                'AuthCode'                       => 'S90370',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => '00',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4320',
                'VoidDate'                       => '',
                'CardMask'                       => '415565******6111',
                'ReqId'                          => '26786247',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '20221031',
                'SysDate'                        => '20221031',
                'F11'                            => '101605',
                'F37'                            => '230423101605',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '16',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '5',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'False',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '0',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '89.244.149.137',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '0',
                'VoidUserCode'                   => '',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'IsVoided'                       => 'false',
                'IsRefunded'                     => 'false',
                'TrxDate'                        => '31.10.2022 23:13',
                'ReturnMessage'                  => 'Onaylandı',
            ],
            'expectedData' => [
                'auth_code'         => 'S90370',
                'order_id'          => '202210312A24',
                'org_order_id'      => null,
                'proc_return_code'  => '00',
                'error_message'     => null,
                'error_code'        => null,
                'ref_ret_num'       => null,
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'pay',
                'masked_number'     => '415565******6111',
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'capture'           => true,
                'capture_time'      => $success1CaptureTime,
                'capture_amount'    => 1.01,
                'first_amount'      => 1.01,
                'transaction_id'    => null,
                'transaction_time'  => $success1CaptureTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        yield 'success_pre_pay' => [
            'responseData' => [
                'RequestGuid'                    => '1000000094948181',
                'InsertDatetime'                 => '19.01.2024 22:07:49',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '202401191C51',
                'RequestIp'                      => '88.152.8.2',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'NonSecure',
                'PurchAmount'                    => '2.01',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'PreAuth',
                'OrgOrderId'                     => '',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => 'V',
                'Lang'                           => 'TR',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '3',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => 'John Doe',
                'IrcDet'                         => '',
                'IrcCode'                        => '',
                'Version'                        => '',
                'TxnStatus'                      => 'Y',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Onaylandı',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => 'Success',
                'AuthCode'                       => 'S18386',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => '00',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4005',
                'VoidDate'                       => '',
                'CardMask'                       => '415565******6111',
                'ReqId'                          => '99712092',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '20240119',
                'SysDate'                        => '20240119',
                'F11'                            => '27445',
                'F37'                            => '401922027445',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '16',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '2',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'False',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '0',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '88.152.8.2',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '0',
                'VoidUserCode'                   => '',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'ArtiTaksit'                     => '0',
                'AuthId'                         => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'IsVoided'                       => 'false',
                'IsRefunded'                     => 'false',
                'TrxDate'                        => '19.01.2024 22:07',
                'ReturnMessage'                  => 'Onaylandı',
            ],
            'expectedData' => [
                'auth_code'         => 'S18386',
                'order_id'          => '202401191C51',
                'org_order_id'      => null,
                'proc_return_code'  => '00',
                'error_message'     => null,
                'error_code'        => null,
                'ref_ret_num'       => null,
                'order_status'      => null,
                'transaction_type'  => 'pre',
                'masked_number'     => '415565******6111',
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'capture'           => false,
                'capture_time'      => null,
                'capture_amount'    => null,
                'first_amount'      => 2.01,
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable('19.01.2024 22:07:49'),
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 3,
            ],
        ];

        $successPrePayAndPostDateTime = new \DateTimeImmutable('19.01.2024 22:13:39');
        yield 'success_pre_pay_and_post_pay' => [
            'responseData' => [
                'RequestGuid'                    => '1000000094938529',
                'InsertDatetime'                 => '19.01.2024 22:13:39',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '2024011926F1',
                'RequestIp'                      => '88.152.8.2',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'NonSecure',
                'PurchAmount'                    => '2.03',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'PostAuth',
                'OrgOrderId'                     => '2024011926F1',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => 'V',
                'Lang'                           => 'TR',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '3',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => '',
                'IrcDet'                         => '',
                'IrcCode'                        => '',
                'Version'                        => '',
                'TxnStatus'                      => 'Y',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Onaylandı',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => 'Success',
                'AuthCode'                       => 'S89375',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => '00',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4005',
                'VoidDate'                       => '',
                'CardMask'                       => '415565******6111',
                'ReqId'                          => '99712640',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '20240119',
                'SysDate'                        => '20240119',
                'F11'                            => '27995',
                'F37'                            => '401922027995',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '16',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '3',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'False',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '27994',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '88.152.8.2',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '0',
                'VoidUserCode'                   => '',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'ArtiTaksit'                     => '0',
                'AuthId'                         => '',
                'CardAcceptorName'               => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'IsVoided'                       => 'false',
                'IsRefunded'                     => 'false',
                'TrxDate'                        => '19.01.2024 22:13',
                'ReturnMessage'                  => 'Onaylandı',
            ],
            'expectedData' => [
                'auth_code'         => 'S89375',
                'order_id'          => '2024011926F1',
                'org_order_id'      => '2024011926F1',
                'proc_return_code'  => '00',
                'error_message'     => null,
                'error_code'        => null,
                'ref_ret_num'       => null,
                'order_status'      => 'PAYMENT_COMPLETED',
                'transaction_type'  => 'post',
                'masked_number'     => '415565******6111',
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'capture'           => true,
                'capture_time'      => $successPrePayAndPostDateTime,
                'capture_amount'    => 2.03,
                'first_amount'      => 2.03,
                'transaction_id'    => null,
                'transaction_time'  => $successPrePayAndPostDateTime,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 3,
            ],
        ];

        $successPayThenCancelDateTime       = new \DateTimeImmutable('19.01.2024 21:34:05');
        $successPayThenCancelCancelDateTime = new \DateTimeImmutable('20240119T213405');

        yield 'success_pay_then_cancel' => [
            'responseData' => [
                'RequestGuid'                    => '1000000094947969',
                'InsertDatetime'                 => '19.01.2024 21:34:05',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '202401194815',
                'RequestIp'                      => '88.152.8.2',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'NonSecure',
                'PurchAmount'                    => '1.01',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'Auth',
                'OrgOrderId'                     => '',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => 'V',
                'Lang'                           => 'TR',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '0',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => 'John Doe',
                'IrcDet'                         => '',
                'IrcCode'                        => '',
                'Version'                        => '',
                'TxnStatus'                      => 'V',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Onaylandı',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => 'Success',
                'AuthCode'                       => 'S29682',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => '00',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4005',
                'VoidDate'                       => '20240119',
                'CardMask'                       => '415565******6111',
                'ReqId'                          => '99709025',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '20240119',
                'SysDate'                        => '20240119',
                'F11'                            => '24380',
                'F37'                            => '401921024380',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '15',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '2',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'False',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '0',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '88.152.8.2',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '213405',
                'VoidUserCode'                   => 'QNB_API_KULLANICI_3DPAY',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'ArtiTaksit'                     => '0',
                'AuthId'                         => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'IsVoided'                       => 'true',
                'IsRefunded'                     => 'false',
                'TrxDate'                        => '19.01.2024 21:34',
                'ReturnMessage'                  => 'Onaylandı',
            ],
            'expectedData' => [
                'auth_code'         => 'S29682',
                'order_id'          => '202401194815',
                'org_order_id'      => null,
                'proc_return_code'  => '00',
                'error_message'     => null,
                'error_code'        => null,
                'ref_ret_num'       => null,
                'order_status'      => 'CANCELED',
                'transaction_type'  => 'pay',
                'masked_number'     => '415565******6111',
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'capture'           => true,
                'capture_time'      => $successPayThenCancelDateTime,
                'capture_amount'    => 1.01,
                'first_amount'      => 1.01,
                'transaction_id'    => null,
                'transaction_time'  => $successPayThenCancelDateTime,
                'cancel_time'       => $successPayThenCancelCancelDateTime,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
            ],
        ];

        yield 'success_pre_pay_then_cancel' => [
            'responseData' => [
                'RequestGuid'                    => '1000000094947988',
                'InsertDatetime'                 => '19.01.2024 21:47:43',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '202401197C78',
                'RequestIp'                      => '88.152.8.2',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'NonSecure',
                'PurchAmount'                    => '1.01',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'PreAuth',
                'OrgOrderId'                     => '',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => 'V',
                'Lang'                           => 'TR',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '3',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => 'John Doe',
                'IrcDet'                         => '',
                'IrcCode'                        => '',
                'Version'                        => '',
                'TxnStatus'                      => 'V',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Onaylandı',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => 'Success',
                'AuthCode'                       => 'S54087',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => '00',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4005',
                'VoidDate'                       => '20240119',
                'CardMask'                       => '415565******6111',
                'ReqId'                          => '99710256',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '20240119',
                'SysDate'                        => '20240119',
                'F11'                            => '25609',
                'F37'                            => '401921025609',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '15',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '2',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'False',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '0',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '88.152.8.2',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '214744',
                'VoidUserCode'                   => 'QNB_API_KULLANICI_3DPAY',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'ArtiTaksit'                     => '0',
                'AuthId'                         => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'IsVoided'                       => 'true',
                'IsRefunded'                     => 'false',
                'TrxDate'                        => '19.01.2024 21:47',
                'ReturnMessage'                  => 'Onaylandı',
            ],
            'expectedData' => [
                'auth_code'         => 'S54087',
                'order_id'          => '202401197C78',
                'org_order_id'      => null,
                'proc_return_code'  => '00',
                'error_message'     => null,
                'error_code'        => null,
                'ref_ret_num'       => null,
                'order_status'      => 'CANCELED',
                'transaction_type'  => 'pre',
                'masked_number'     => '415565******6111',
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'approved',
                'status_detail'     => 'approved',
                'capture'           => false,
                'capture_time'      => null,
                'capture_amount'    => null,
                'first_amount'      => 1.01,
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable('19.01.2024 21:47:43'),
                'cancel_time'       => new \DateTimeImmutable('20240119T214744'),
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 3,
            ],
        ];


        yield 'fail_order_not_found' => [
            'responseData' => [
                'RequestGuid'                    => '0',
                'InsertDatetime'                 => '1.01.0001 00:00:00',
                'MbrId'                          => '5',
                'MerchantID'                     => '085300000009704',
                'OrderId'                        => '202210312A242',
                'RequestIp'                      => '89.244.149.137',
                'RequestStat'                    => '1,10',
                'SecureType'                     => 'Inquiry',
                'PurchAmount'                    => '',
                'Exponent'                       => '2',
                'Currency'                       => '949',
                'Description'                    => '',
                'OkUrl'                          => '',
                'FailUrl'                        => '',
                'PayerTxnId'                     => '',
                'PayerAuthenticationCode'        => '',
                'Eci'                            => '',
                'MD'                             => '',
                'Hash'                           => '',
                'TerminalID'                     => 'VS010481',
                'TxnType'                        => 'OrderInquiry',
                'OrgOrderId'                     => '202210312A242',
                'SubMerchantCode'                => '',
                'recur_frequency'                => '',
                'recur_expiry'                   => '',
                'CardType'                       => '',
                'Lang'                           => 'tr',
                'BonusAmount'                    => '',
                'InstallmentCount'               => '0',
                'Rnd'                            => '',
                'AlphaCode'                      => 'TL',
                'Ecommerce'                      => '1',
                'MrcCountryCode'                 => '792',
                'MrcName'                        => '3D PAY TEST ISYERI',
                'MerchantHomeUrl'                => 'https://vpostest.qnbfinansbank.com/',
                'CardHolderName'                 => '',
                'IrcDet'                         => 'Seçili İşlem Bulunamadı!',
                'IrcCode'                        => '99961',
                'Version'                        => '',
                'TxnStatus'                      => 'P',
                'CavvAlg'                        => '',
                'ParesVerified'                  => '',
                'ParesSyntaxOk'                  => '',
                'ErrMsg'                         => 'Seçili İşlem Bulunamadı!',
                'VendorDet'                      => '',
                'D3Status'                       => '-1',
                'TxnResult'                      => '',
                'AuthCode'                       => '',
                'HostRefNum'                     => '',
                'ProcReturnCode'                 => 'V013',
                'ReturnUrl'                      => '',
                'ErrorData'                      => '',
                'BatchNo'                        => '4320',
                'VoidDate'                       => '',
                'CardMask'                       => '',
                'ReqId'                          => '26786448',
                'UsedPoint'                      => '0',
                'SrcType'                        => 'VPO',
                'RefundedAmount'                 => '0',
                'RefundedPoint'                  => '0',
                'ReqDate'                        => '0',
                'SysDate'                        => '0',
                'F11'                            => '101906',
                'F37'                            => '',
                'IsRepeatTxn'                    => '',
                'CavvResult'                     => '',
                'VposElapsedTime'                => '0',
                'BankingElapsedTime'             => '0',
                'SocketElapsedTime'              => '0',
                'HsmElapsedTime'                 => '0',
                'MpiElapsedTime'                 => '0',
                'hasOrderId'                     => 'True',
                'TemplateType'                   => '0',
                'HasAddressCount'                => 'False',
                'IsPaymentFacilitator'           => 'False',
                'MerchantCountryCode'            => '',
                'OrgTxnType'                     => '',
                'F11_ORG'                        => '0',
                'F12_ORG'                        => '0',
                'F13_ORG'                        => '',
                'F22_ORG'                        => '0',
                'F25_ORG'                        => '0',
                'MTI_ORG'                        => '0',
                'DsBrand'                        => '',
                'IntervalType'                   => '0',
                'IntervalDuration'               => '0',
                'RepeatCount'                    => '0',
                'CustomerCode'                   => '',
                'RequestMerchantDomain'          => '',
                'RequestClientIp'                => '89.244.149.137',
                'ResponseRnd'                    => '',
                'ResponseHash'                   => '',
                'BankInternalResponseCode'       => '',
                'BankInternalResponseMessage'    => '',
                'BankInternalResponseSubcode'    => '',
                'BankInternalResponseSubmessage' => '',
                'BayiKodu'                       => '',
                'VoidTime'                       => '0',
                'VoidUserCode'                   => '',
                'PaymentLinkId'                  => '0',
                'ClientId'                       => '',
                'IsQRValid'                      => '',
                'IsFastValid'                    => '',
                'IsQR'                           => '',
                'IsFast'                         => '',
                'QRRefNo'                        => '',
                'FASTGonderenKatilimciKodu'      => '',
                'FASTAlanKatilimciKodu'          => '',
                'FASTReferansNo'                 => '',
                'FastGonderenIBAN'               => '',
                'FASTGonderenAdi'                => '',
                'MobileECI'                      => '',
                'HubConnId'                      => '',
                'WalletData'                     => '',
                'Tds2dsTransId'                  => '',
                'Is3DHost'                       => '',
                'PAYFORFROMXMLREQUEST'           => '1',
                'SESSION_SYSTEM_USER'            => '0',
            ],
            'expectedData' => [
                'auth_code'         => null,
                'order_id'          => '202210312A242',
                'org_order_id'      => '202210312A242',
                'proc_return_code'  => 'V013',
                'error_message'     => 'Seçili İşlem Bulunamadı!',
                'ref_ret_num'       => null,
                'order_status'      => null,
                'transaction_type'  => 'status',
                'masked_number'     => null,
                'currency'          => PosInterface::CURRENCY_TRY,
                'status'            => 'declined',
                'status_detail'     => 'reject',
                'transaction_time'  => null,
                'transaction_id'    => null,
                'capture_time'      => null,
                'capture'           => null,
                'capture_amount'    => null,
                'error_code'        => null,
                'first_amount'      => null,
                'cancel_time'       => null,
                'refund_amount'     => null,
                'refund_time'       => null,
                'installment_count' => 0,
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
                        'transaction_id'   => '20221031D388',
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
                        'transaction_id'   => '20221031D388',
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
                        'AuthCode'       => 'S37109',
                        'HostRefNum'     => '230423104777',
                        'ProcReturnCode' => 'V014',
                        'TransId'        => '20221031714F',
                        'ErrMsg'         => 'Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.',
                        'CardHolderName' => '',
                    ],
                    'expectedData' => [
                        'order_id'         => '20221031714F',
                        'auth_code'        => null,
                        'ref_ret_num'      => '230423104777',
                        'proc_return_code' => 'V014',
                        'transaction_id'   => '20221031714F',
                        'error_code'       => 'V014',
                        'error_message'    => 'Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.',
                        'status'           => 'declined',
                        'status_detail'    => 'request_rejected',
                    ],
                ],
            ];
    }

    public static function orderHistoryTestDataProvider(): array
    {
        return [
            'success_pre_pay_post_pay_then_cancel' => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/success_pre_pay_post_pay_then_cancel_response.json'), true),
                'expectedData' => [
                    'order_id'         => '2024012107B7',
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => null,
                    'status_detail'    => null,
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S65465',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T17:39:02'),
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PRE_AUTH_COMPLETED',
                            'transaction_type' => 'pre',
                            'first_amount'     => 2.01,
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => false,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                        [
                            'auth_code'        => 'S75952',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T17:39:06'),
                            'capture_time'     => new \DateTimeImmutable('2024-01-21T17:39:06'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'post',
                            'first_amount'     => 2.03,
                            'capture_amount'   => 2.03,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                        [
                            'auth_code'        => 'S10420',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T17:39:16'),
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'cancel',
                            'first_amount'     => 2.03,
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                    ],
                ],
            ],
            'success_pay'                          => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/success_pay_response.json'), true),
                'expectedData' => [
                    'order_id'         => '202401212A22',
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S90726',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T21:40:47'),
                            'capture_time'     => new \DateTimeImmutable('2024-01-21T21:40:47'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                    ],
                ],
            ],
            'success_pre_pay'                      => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/success_pre_pay_response.json'), true),
                'expectedData' => [
                    'order_id'         => '2024012186F9',
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'status_detail'    => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S95711',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T21:59:31'),
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PRE_AUTH_COMPLETED',
                            'transaction_type' => 'pre',
                            'first_amount'     => 2.01,
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => false,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                    ],
                ],
            ],
            'success_pay_refund_fail'              => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/success_pay_refund_fail_response.json'), true),
                'expectedData' => [
                    'order_id'         => '202401211C79',
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => null,
                    'status_detail'    => null,
                    'trans_count'      => 2,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S83066',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-01-21T22:14:23'),
                            'capture_time'     => new \DateTimeImmutable('2024-01-21T22:14:23'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => 'V014',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'refund',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => 'V014',
                            'status_detail'    => 'request_rejected',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                        ],
                    ],
                ],
            ],
            'fail_order_not_found'                 => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/fail_order_not_found_response.json'), true),
                'expectedData' => [
                    'order_id'         => '202401010C2022',
                    'proc_return_code' => 'V013',
                    'error_code'       => 'V013',
                    'error_message'    => 'Seçili İşlem Bulunamadı!',
                    'status'           => 'declined',
                    'status_detail'    => 'reject',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
        ];
    }

    public static function historyTestDataProvider(): array
    {
        return [
            'daily_history_1' => [
                'responseData' => \json_decode(\file_get_contents(__DIR__.'/../../test_data/payfor/history/daily_history.json'), true),
                'expectedData' => [
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => null,
                    'status_detail'    => null,
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'        => null,
                            'proc_return_code' => 'V000',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => 'V000',
                            'status_detail'    => null,
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                            'order_id'         => '3450201880',
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => 'V000',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => 'V000',
                            'status_detail'    => null,
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                            'order_id'         => '1171158618',
                        ],
                        [
                            'auth_code'        => 'S70708',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new \DateTimeImmutable('2024-03-14T21:40:18'),
                            'capture_time'     => new \DateTimeImmutable('2024-03-14T21:40:18'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 100.0,
                            'capture_amount'   => 100.0,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'status_detail'    => 'approved',
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415956******7732',
                            'order_id'         => '1427731461',
                        ],
                    ],
                ],
            ],
        ];
    }
}
