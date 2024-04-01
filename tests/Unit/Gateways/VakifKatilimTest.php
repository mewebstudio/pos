<?php
/**
 * @license MIT
 */

namespace Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\VakifKatilimPos
 */
class VakifKatilimTest extends TestCase
{
    use HttpClientTestTrait;

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

    /** @var HttpClient & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    /** @var SerializerInterface & MockObject */
    private MockObject $serializerMock;

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
                'payment_api' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d'  => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
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

        $this->requestMapperMock   = $this->createMock(VakifKatilimPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new VakifKatilimPos(
            $this->config,
            $this->account,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->serializerMock,
            $this->eventDispatcherMock,
            $this->httpClientMock,
            $this->loggerMock,
        );

        $this->pos->setTestMode(true);

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
        $this->requestMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '0949']);
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiURL(string $txType, ?string $orderTxType, string $paymentModel, string $expected): void
    {
        $actual = $this->pos->getApiURL($txType, $paymentModel, $orderTxType);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider getApiUrlExceptionDataProvider
     */
    public function testGetApiURLException(string $txType, string $paymentModel, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);

        $this->pos->getApiURL($txType, $paymentModel);
    }

    /**
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse(): void
    {
        $response     = 'bank-api-html-response';
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $card         = $this->card;

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['form-data'], $txType)
            ->willReturn('encoded-request-data');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with($response, $txType)
            ->willReturn(['form_inputs' => ['form-inputs'], 'gateway' => 'form-action-url']);
        $this->prepareClient(
            $this->httpClientMock,
            $response,
            'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
            [
                'body'    => 'encoded-request-data',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ],
        );

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch');

        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with(
                $this->pos->getAccount(),
                $this->order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn(['form-data']);

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                ['form-inputs'],
                $paymentModel,
                $txType,
                'form-action-url',
                $card
            )
            ->willReturn(['3d-form-data']);
        $result = $this->pos->get3DFormData($this->order, $paymentModel, $txType, $card);

        $this->assertSame(['3d-form-data'], $result);
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
                'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType);

        $this->assertSame(['formData'], $actual);
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
    ): void
    {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $request->request->all())
                ->willReturn(true);
        }

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
            $this->prepareClient(
                $this->httpClientMock,
                'response-body',
                'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
                [
                    'body'    => 'request-body',
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=UTF-8',
                    ],
                ]
            );

            $this->serializerMock->expects(self::once())
                ->method('encode')
                ->with($create3DPaymentRequestData, $txType)
                ->willReturn('request-body');

            $this->serializerMock->expects(self::exactly(1))
                ->method('decode')
                ->willReturnMap([
                    [
                        'response-body',
                        $txType,
                        $paymentResponse,
                    ],
                ]);

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
            $this->serializerMock->expects(self::never())
                ->method('encode');
            $this->serializerMock->expects(self::once())
                ->method('decode')
                ->with(urldecode($request->request->get('AuthenticationResponse')), $txType)
                ->willReturn($request->request->all());
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
    public function testMakeRegularPayment(array $order, string $txType, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $card    = $this->card;
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn(['createNonSecurePaymentRequestData']);
        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createNonSecurePaymentRequestData'], $txType)
            ->willReturn('request-body');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['paymentResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPayment($order, $card, $txType);
    }

    /**
     * @dataProvider makeRegularPostAuthPaymentDataProvider
     */
    public function testMakeRegularPostAuthPayment(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_PAY_POST_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePostAuthPaymentRequestData')
            ->with($account, $order)
            ->willReturn(['createNonSecurePostAuthPaymentRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createNonSecurePostAuthPaymentRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['paymentResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPostPayment($order);
    }


    /**
     * @dataProvider statusRequestDataProvider
     */
    public function testStatusRequest(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_STATUS;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn(['createStatusRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createStatusRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );


        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['decodedResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->status($order);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCancelRequest(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_CANCEL;

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn(['createCancelRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createCancelRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['decodedResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->cancel($order);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testRefundRequest(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_REFUND;

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order)
            ->willReturn(['createRefundRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createRefundRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['decodedResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->refund($order);
    }


    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testHistoryRequest(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_HISTORY;

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
            ->with($account, $order)
            ->willReturn(['createHistoryRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createHistoryRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['decodedResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->history($order);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testOrderHistoryRequest(array $order, string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_ORDER_HISTORY;

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn(['createOrderHistoryRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createOrderHistoryRequestData'], $txType)
            ->willReturn('request-body');

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'body'    => 'request-body',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', $txType)
            ->willReturn(['decodedResponse']);

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->orderHistory($order);
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
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

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizaten',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizatenClose',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrderByMerchantOrderId',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_ORDER_HISTORY,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrder',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationDrawBack',
            ],
        ];
    }

    public static function getApiUrlExceptionDataProvider(): array
    {
        return [
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'exception_class' => UnsupportedTransactionTypeException::class,
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
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate',
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizaten',
            ],
        ];
    }

    public static function makeRegularPostAuthPaymentDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizatenClose',
            ],
        ];
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrderByMerchantOrderId',
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            'pay_order'      => [
                'order'   => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal',
            ],
            'pay_auth_order' => [
                'order'   => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationReversal',
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'pay_order'      => [
                'order'   => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack',
            ],
            'pay_auth_order' => [
                'order'   => [
                    'id'               => '2020110828BC',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationDrawBack',
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrder',
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrder',
            ],
        ];
    }
}
