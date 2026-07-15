<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\GarantiPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(GarantiPos::class)]
#[CoversClass(AbstractGateway::class)]
class GarantiPosTest extends TestCase
{
    private GarantiPosAccount $account;

    /** @var RequestDataMapperInterface & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var HttpClientStrategyInterface & MockObject */
    private MockObject $httpClientStrategyMock;

    /** @var HttpClientInterface & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    private array $config;

    /** @var GarantiPos */
    private PosInterface $pos;

    private CreditCardInterface $card;

    private GarantiPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'Garanti',
            'class'             => GarantiPos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
            ],
        ];

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->requestValueMapper     = new GarantiPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5555444433332222',
            '21',
            '12',
            '122',
            'ahmet',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertCount(count($this->requestValueMapper->getCurrencyMappings()), $this->pos->getCurrencies());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    /**
     * @return void
     */
    public function testInitOnTestMode(): void
    {
        $config = [
            'name'              => 'Garanti',
            'class'             => GarantiPos::class,
            'gateway_configs'   => [
                'test_mode' => true,
            ],
            'gateway_endpoints' => [
                'gateway_3d' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
            ],
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('setTestMode')
            ->with(true);

        $this->loggerMock->expects(self::never())
            ->method('warning');

        $this->pos = $this->createGateway($config);

        $this->assertTrue($this->pos->isTestMode());
    }

    #[TestWith([true])]
    public function testGet3DFormData(
        bool $isWithCard
    ): void {
        $card         = $isWithCard ? $this->card : null;
        $paymentModel = $isWithCard ? PosInterface::MODEL_3D_SECURE : PosInterface::MODEL_3D_HOST;
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $this->config['gateway_endpoints']['gateway_3d'],
                $card
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame(['formData'], $actual);
    }

    #[DataProvider('threeDFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        bool    $createWithoutCard,
        string  $expectedExceptionClass,
        string  $expectedExceptionMsg,
        ?string $formFormat = null
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, $createWithoutCard, $formFormat);
    }

    #[DataProvider('make3DPaymentDataProvider')]
    public function testMake3DPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $gatewayResponseData)
                ->willReturn(true);
        }

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);

        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];

        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $this->configureClientResponse(
                $txType,
                $create3DPaymentRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_3D_SECURE
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }


    #[DataProvider('make3DPaymentWithoutHashCheckDataProvider')]
    public function testMake3DPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        $config = $this->config;
        $config += [
            'gateway_configs' => [
                'disable_3d_hash_check' => true,
            ],
        ];

        $pos = $this->createGateway($config);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);

        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];

        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $this->configureClientResponse(
                $txType,
                $create3DPaymentRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_3D_SECURE
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPaymentHashMismatchException(): void
    {
        $data = [
            'xid'           => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
            'md'            => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==',
            'terminalid'    => '30691298',
            'oid'           => '20221101295D',
            'rnd'           => 'Nvx8y+0R3sR5mfDVLtVD',
            'hash'          => 'K1eaT12s4oPbvQDfA6YIMCfH6HQ=',
            'hashparams'    => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
            'hashparamsval' => '3069129820221101295D1jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==Nvx8y+0R3sR5mfDVLtVD',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPaymentData');
        $this->requestMapperMock->expects(self::never())
            ->method('create3DPaymentRequestData');
        $this->eventDispatcherMock->expects(self::never())
            ->method('dispatch');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], PosInterface::TX_TYPE_PAY_AUTH, null, $data);
    }

    public function testMake3DHostPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], PosInterface::TX_TYPE_PAY_AUTH, null, ['abc']);
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment(): void
    {
        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $responseData = ['$responseData'];
        $order        = ['id' => '123'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($responseData, $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $responseData);

        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    #[DataProvider('makeRegularPaymentDataProvider')]
    public function testMakeRegularPayment(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
        $card        = $this->card;
        $requestData = ['createNonSecurePaymentRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn($requestData);

        $decodedResponse = ['paymentResponse'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $card);
    }

    #[DataProvider('makeRegularPostAuthPaymentDataProvider')]
    public function testMakeRegularPostAuthPayment(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_PAY_POST_AUTH;
        $requestData = ['createNonSecurePostAuthPaymentRequestData'];
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePostAuthPaymentRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType);
    }

    #[DataProvider('statusRequestDataProvider')]
    public function testStatusRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = ['createStatusRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->status($order);
    }

    #[DataProvider('cancelRequestDataProvider')]
    public function testCancelRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_CANCEL;
        $requestData = ['createCancelRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->cancel($order);
    }

    #[DataProvider('refundRequestDataProvider')]
    public function testRefundRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_REFUND;
        $requestData = ['createRefundRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
    }

    #[DataProvider('orderHistoryRequestDataProvider')]
    public function testOrderHistoryRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_ORDER_HISTORY;
        $requestData = ['createOrderHistoryRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->orderHistory($order);
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            '3d_auth_fail_1'               => [
                'order'               => [],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'apiversion'     => '512',
                    'authcode'       => '',
                    'cavv'           => '',
                    'hash'           => 'FD9BF014BFBC3D977B123AE84247CE3F639913644429304C4EE108C0F40212853628CAECAADB798EE73D467F50C3B5D90FE0F3B921EDAA94B6E2EA888F9FE9B7',
                    'hashparams'     => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'  => '30691298202409078CF30aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkMcb8vBvm21xQz107TS6WKHVjLlrZP9AMx7KFu8jZRZA3WtZxMUuaUynWLxQGsVrw1vTKIofUQ0dw2P/jVVvPMX/RxI7Bpjvo/pZp3Nmbj2wd1W146UhNNmge7eA+hdrNSHuPp7PmjAihyZEPujAi3Q==6ga2Y3buKZ3ZcJSC7uE6',
                    'hostmsg'        => '',
                    'md'             => 'aW5kZXg6MDIYjoYTHmZE91tOHZxS3PEkMcb8vBvm21xQz107TS6WKHVjLlrZP9AMx7KFu8jZRZA3WtZxMUuaUynWLxQGsVrw1vTKIofUQ0dw2P/jVVvPMX/RxI7Bpjvo/pZp3Nmbj2wd1W146UhNNmge7eA+hdrNSHuPp7PmjAihyZEPujAi3Q==',
                    'mderrormessage' => '',
                    'secure3dhash'   => '9F3027C22FB3485484144993',
                ],
                'paymentResponse'     => [],
                'expected'            => [
                    'order_id'       => '202409078CF3',
                    'transaction_id' => null,
                    'auth_code'      => null,
                ],
                'is3DSuccess'         => false,
                'isSuccess'           => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'               => [],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'xid'           => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'md'            => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==',
                    'rnd'           => 'Nvx8y+0R3sR5mfDVLtVD',
                    'hash'          => 'K1eaT12s4oPbvQDfA6YIMCfH6HQ=',
                    'hashparams'    => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval' => '3069129820221101295D1jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51ANsgVc6NOy8kHpgH0Bj2jGdc4n47VV2IxRcLSwiw3+DC4zpyj2qtCo8LA5ACL2pHmusSpDmp+kAJOIQTFpsCfJ53tob4+xTUbctQuxBd4u+Bqs1looyNEeg==Nvx8y+0R3sR5mfDVLtVD',
                ],
                'paymentResponse'     => [
                    'Mode'     => '',
                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                ],
                'expected'            => [
                    'order_id'       => '20221101295D',
                    'transaction_id' => '20221101295D',
                ],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => [],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'xid'            => '748ac354-4bfe-4b40-aa12-5ea025b7399b',
                    'mdstatus'       => '1',
                    'mderrormessage' => 'Y-status/Challenge authentication via ACS: https://gbemv3dsecure.garanti.com.tr/web/creq',
                ],
                'paymentResponse'     => [
                    'Mode'     => null,
                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],

                ],
                'expected'            => [
                    'order_id'       => '202312238064',
                    'transaction_id' => '202312238064',
                    'status'         => 'approved',
                ],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            '3d_auth_success_payment_fail' => [
                'order'               => [],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'xid'            => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
                    'mdstatus'       => '1',
                    'mderrormessage' => 'Authenticated',
                ],
                'paymentResponse'     => [
                    'Mode'     => '',
                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                ],
                'expected'            => [
                    'order_id'       => '20221101295D',
                    'transaction_id' => '20221101295D',
                    'auth_code'      => null,
                    'status'         => 'declined',
                ],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => [],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'xid'            => '748ac354-4bfe-4b40-aa12-5ea025b7399b',
                    'mdstatus'       => '1',
                    'mderrormessage' => 'Y-status/Challenge authentication via ACS: https://gbemv3dsecure.garanti.com.tr/web/creq',
                    'txnstatus'      => null,
                ],
                'paymentResponse'     => [
                    'Mode'     => null,
                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                ],
                'expected'            => [
                    'order_id'       => '202312238064',
                    'transaction_id' => '202312238064',
                    'md_status'      => '1',
                    'status'         => 'approved',

                ],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function makeRegularPaymentDataProvider(): array
    {
        return [
            [
                'order'  => [
                    'id' => '2020110828BC',
                ],
                'txType' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            [
                'order'  => [
                    'id' => '2020110828BC',
                ],
                'txType' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            ],
        ];
    }

    public static function makeRegularPostAuthPaymentDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            '3d_secure_without_card'  => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_pay_without_card'     => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'non_payment_tx_type'     => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'unsupported_form_format' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }


    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new GarantiPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->cryptMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock,
        );
    }

    private function configureClientResponse(
        string              $txType,
        array               $requestData,
        array               $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($txType, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(fn (array $requestData): bool => $requestData['test-update-request-data-with-event'] === true),
                $order,
                $apiUrl,
                $account
            )->willReturn($decodedResponse);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(
                    function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                        $updatedRequestDataPreparedEvent = $dispatchedEvent;

                        return $this->pos::class === $dispatchedEvent->getGatewayClass()
                            && $txType === $dispatchedEvent->getTxType()
                            && $requestData === $dispatchedEvent->getRequestData()
                            && $order === $dispatchedEvent->getOrder()
                            && $paymentModel === $dispatchedEvent->getPaymentModel();
                    }
                )
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
