<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\VakifKatilimPos
 * @covers \Mews\Pos\Gateways\AbstractHttpGateway
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class VakifKatilimTest extends TestCase
{
    private KuveytPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var VakifKatilimPos */
    private PosInterface $pos;

    /** @var VakifKatilimPosRequestDataMapper & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var HttpClientInterface & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    private VakifKatilimPosRequestValueMapper $requestValueMapper;

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'Vakıf Katılım',
            'class'             => VakifKatilimPos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
            ],
        ];

        $this->account = AccountFactory::createKuveytPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
        ];

        $this->requestValueMapper  = new VakifKatilimPosRequestValueMapper();
        $this->requestMapperMock   = $this->createMock(VakifKatilimPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $serializerMock            = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClientInterface::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new VakifKatilimPos(
            $this->config,
            $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $serializerMock,
            $this->eventDispatcherMock,
            $this->httpClientMock,
            $this->loggerMock,
        );

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertCount(count($this->requestValueMapper->getCurrencyMappings()), $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
    }

    /**
     * @return void
     */
    public function testGet3DFormDataSuccessResponse(): void
    {
        $response     = 'bank-api-html-response';
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $card         = $this->card;
        $requestData  = ['form-data'];
        $order        = $this->order;

        $this->configureClientResponse(
            $txType,
            $requestData,
            $response,
            $order,
            $paymentModel,
            $this->config['gateway_endpoints']['gateway_3d'],
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with(
                $this->pos->getAccount(),
                $this->order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn($requestData);

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $result = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame($response, $result);
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData(): void
    {
        $order        = ['id' => '124'];
        $paymentModel = PosInterface::MODEL_3D_HOST;
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $this->config['gateway_endpoints']['gateway_3d'],
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType);

        $this->assertSame(['formData'], $actual);
    }

    /**
     * @dataProvider threeDFormDataBadInputsProvider
     */
    public function testGet3DFormDataWithBadInputs(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        bool   $createWithoutCard,
        string $expectedExceptionClass,
        string $expectedExceptionMsg
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, $createWithoutCard);
    }

    /**
     * @dataProvider make3DPaymentDataProvider
     */
    public function testMake3DPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $paymentResponse,
        array   $expectedResponse,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void {
        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($request->request->all())
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
                ->with($this->account, $order, $txType, $request->request->all())
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
                ->with($request->request->all(), $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($request->request->all(), null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $this->pos->make3DPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DHostPayment(): void
    {
        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $responseData = ['$responseData'];
        $request      = Request::create('', 'POST', $responseData);
        $order        = ['id' => '123'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($request->request->all(), $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $pos->make3DHostPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DPayPayment(): void
    {
        $request = Request::create('', 'POST');

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider makeRegularPaymentDataProvider
     */
    public function testMakeRegularPayment(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
        $card        = $this->card;
        $requestData = ['createNonSecurePaymentRequestData'];
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPayment($order, $card, $txType);
    }

    /**
     * @dataProvider makeRegularPostAuthPaymentDataProvider
     */
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPostPayment($order);
    }


    /**
     * @dataProvider statusRequestDataProvider
     */
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->status($order);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->cancel($order);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testRefundRequest(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
    }


    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testHistoryRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $requestData = ['createHistoryRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->history($order);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->orderHistory($order);
    }

    /**
     * @dataProvider customQueryRequestDataProvider
     */
    public function testCustomQueryRequest(array $requestData, ?string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_CUSTOM_QUERY;

        $updatedRequestData = $requestData + [
                'abc' => 'def',
            ];
        $this->requestMapperMock->expects(self::once())
            ->method('createCustomQueryRequestData')
            ->with($account, $requestData)
            ->willReturn($updatedRequestData);

        $this->configureClientResponse(
            $txType,
            $updatedRequestData,
            ['decodedResponse'],
            $requestData,
            PosInterface::MODEL_NON_SECURE,
            $apiUrl,
            $account
        );

        $this->pos->customQuery($requestData, $apiUrl);
    }


    public function testCustomQueryRequestWithoutAPIurl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pos->customQuery(['ac' => 'aas']);
    }

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/MailOrderSale',
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            '3d_auth_fail' => [
                'order'           => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail1']['order'],
                'txType'          => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail1']['threeDResponseData']
                ),
                'paymentResponse' => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail1']['paymentData'],
                'expected'        => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail1']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'auth_success' => [
                'order'           => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
                ),
                'paymentResponse' => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => VakifKatilimPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function makeRegularPaymentDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
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
            'pay_order'      => [
                'order' => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                ],
            ],
            'pay_auth_order' => [
                'order' => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'pay_order'                => [
                'order'  => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                ],
                'txType' => PosInterface::TX_TYPE_REFUND,
            ],
            'pay_auth_order'           => [
                'order'  => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                ],
                'txType' => PosInterface::TX_TYPE_REFUND,
            ],
            'pay_order_partial_refund' => [
                'order'  => [
                    'id'               => '2020110828BC',
                    'order_amount'     => 10,
                    'amount'           => 5,
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                ],
                'txType' => PosInterface::TX_TYPE_REFUND_PARTIAL,
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
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
            '3d_secure_without_card'    => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\VakifKatilimPos ödeme altyapıda [pay] işlem tipi [regular, 3d, 3d_host] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay].',
            ],
            'unsupported_3d_secure_tx'  => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay].',
            ],
            'unsupported_3d_host_tx'    => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay].',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay]',
            ],
            'post_auth_tx_type'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay]',
            ],
        ];
    }

    private function configureClientResponse(
        string              $txType,
        array               $requestData,
        $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(function (array $requestData) {
                    return $requestData['test-update-request-data-with-event'] === true;
                }),
                $order,
                $apiUrl,
                $account,
                true,
                !\is_string($decodedResponse)
            )->willReturn($decodedResponse);


        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(
                    function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                        $updatedRequestDataPreparedEvent = $dispatchedEvent;

                        return get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                            && $txType === $dispatchedEvent->getTxType()
                            && $requestData === $dispatchedEvent->getRequestData()
                            && $order === $dispatchedEvent->getOrder()
                            && $paymentModel === $dispatchedEvent->getPaymentModel();
                    }
                )
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?\Mews\Pos\Event\RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
