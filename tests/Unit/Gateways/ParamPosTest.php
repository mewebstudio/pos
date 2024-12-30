<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ParamPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ParamPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use Mews\Pos\Tests\Unit\Serializer\ParamPosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\ParamPos
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class ParamPosTest extends TestCase
{
    use HttpClientTestTrait;

    private ParamPosAccount $account;

    private array $config;

    /** @var ParamPos */
    private PosInterface $pos;

    /** @var RequestDataMapperInterface & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    public MockObject $responseMapperMock;

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

    private CreditCardInterface $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'param-pos',
            'class'             => ParamPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
        ];

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->requestMapperMock   = $this->createMock(ParamPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new ParamPos(
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
        $this->requestMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '949']);
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertSame($this->config['gateway_endpoints']['gateway_3d_host'], $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST));
        $this->assertSame($this->config['gateway_endpoints']['gateway_3d'], $this->pos->get3DGatewayURL());
        $this->assertSame($this->config['gateway_endpoints']['payment_api'], $this->pos->getApiURL());
    }

    /**
     * @return void
     */
    public function testSetTestMode(): void
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        array  $requestData,
        string $encodedRequestData,
        string $responseData,
        array  $decodedResponseData,
        string $formData
    ): void
    {

        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'TP_WMD_UCD',
            'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            $requestData,
            $encodedRequestData,
            $responseData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame($actual, $formData);
    }

    /**
     * @dataProvider threeDFormDataFailResponseProvider
     */
    public function testGet3DFormDataFailResponse(
        array  $order,
        string $paymentModel,
        string $txType,
        array  $requestData,
        string $encodedRequestData,
        string $responseData,
        array  $decodedResponseData
    ): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'TP_WMD_UCD',
            'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            $requestData,
            $encodedRequestData,
            $responseData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(\RuntimeException::class);
        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card);
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
        string $expectedExceptionClass
    ): void
    {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);

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

            $this->configureClientResponse(
                $txType,
                $this->config['gateway_endpoints']['payment_api'],
                $create3DPaymentRequestData,
                'request-body',
                'response-body',
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
            $this->serializerMock->expects(self::never())
                ->method('encode');
            $this->serializerMock->expects(self::never())
                ->method('decode');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $this->pos->make3DPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DPaymentHashMismatchException(): void
    {
        $data    = ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'];
        $request = Request::create('', 'POST', $data);

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
        $this->serializerMock->expects(self::never())
            ->method('encode');
        $this->serializerMock->expects(self::never())
            ->method('decode');
        $this->eventDispatcherMock->expects(self::never())
            ->method('dispatch');

        $this->expectException(HashMismatchException::class);
        $this->pos->make3DPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment(): void
    {
        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $responseData = ['$responseData'];
        $request      = Request::create('', 'POST', $responseData);
        $order        = ['id' => '123'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($request->request->all(), $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $pos->make3DPayPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
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

    /**
     * @dataProvider makeRegularPaymentDataProvider
     */
    public function testMakeRegularPayment(array $order, string $txType, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testMakeRegularPostAuthPayment(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testStatusRequest(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testCancelRequest(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testRefundRequest(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testHistoryRequest(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testOrderHistoryRequest(array $order, string $apiUrl): void
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
            $apiUrl,
            $requestData,
            'request-body',
            'response-body',
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    public function testCustomQueryRequest(array $requestData, ?string $apiUrl, string $expectedApiUrl): void
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
            $expectedApiUrl,
            $updatedRequestData,
            'request-body',
            'response-body',
            ['decodedResponse'],
            $requestData,
            PosInterface::MODEL_NON_SECURE
        );

        $this->pos->customQuery($requestData, $apiUrl);
    }

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData'      => [
                    'id' => '2020110828BC',
                ],
                'api_url'          => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx/xxxx',
                'expected_api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx/xxxx',
            ],
            [
                'requestData'      => [
                    'id' => '2020110828BC',
                ],
                'api_url'          => null,
                'expected_api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                  => [
                'order'           => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['order'],
                'txType'          => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['threeDResponseData']
                ),
                'paymentResponse' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['paymentData'],
                'expected'        => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'order_number_already_exist' => [
                'order'           => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['order_number_already_exist']['order'],
                'txType'          => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['order_number_already_exist']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['order_number_already_exist']['threeDResponseData']
                ),
                'paymentResponse' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['order_number_already_exist']['paymentData'],
                'expected'        => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['order_number_already_exist']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'                    => [
                'order'           => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
                ),
                'paymentResponse' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
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
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
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
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
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
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
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
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
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
                'api_url' => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
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
                'create_without_card'    => false,
                'expectedExceptionClass' => \InvalidArgumentException::class,
            ],
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => \InvalidArgumentException::class,
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => true,
                'expectedExceptionClass' => \LogicException::class,
            ],
        ];
    }

    private function configureClientResponse(
        string $txType,
        string $soapMethod,
        string $apiUrl,
        array  $requestData,
        string $encodedRequestData,
        string $responseContent,
        array  $decodedResponse,
        array  $order,
        string $paymentModel
    ): void
    {
        $updatedRequestDataPreparedEvent = null;

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($this->logicalAnd($this->arrayHasKey('test-update-request-data-with-event')), $soapMethod)
            ->willReturn($encodedRequestData);

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with($responseContent, $txType)
            ->willReturn($decodedResponse);

        $this->prepareClient(
            $this->httpClientMock,
            $responseContent,
            $apiUrl,
            [
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
                'body'    => $encodedRequestData,
            ],
        );

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                    $updatedRequestDataPreparedEvent = $dispatchedEvent;

                    return get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $requestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel();
                })
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?\Mews\Pos\Event\RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }

    public static function threeDFormDataFailResponseProvider(): iterable
    {
        $responseTestData = \iterator_to_array(ParamPosSerializerTest::decodeDataProvider());
        yield 'bad_request' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => 'encoded-request-data',
            'responseData'        => $responseTestData['3d_form_success']['input'],
            'decodedResponseData' => [
                "soap:Fault" => [
                    "faultcode"   => "soap:Client",
                    "faultstring" => "Unable to handle request without a valid action parameter. Please supply a valid soap action.",
                    "detail"      => "",
                ],
            ],
        ];

    }

    public static function threeDFormDataProvider(): iterable
    {
        $responseTestData = \iterator_to_array(ParamPosSerializerTest::decodeDataProvider());
        yield '3d_secure' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => 'encoded-request-data',
            'responseData'        => $responseTestData['3d_form_success']['input'],
            'decodedResponseData' => $responseTestData['3d_form_success']['expected'],
            'formData'            => $responseTestData['3d_form_success']['expected']['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'],
        ];


//        yield '3d_host' => [
//            'order'               => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
//            'paymentModel'        => PosInterface::MODEL_3D_HOST,
//            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
//            'isWithCard'          => false,
//            'requestData'         => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
//            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
//            'responseData'        => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['input'],
//            'decodedResponseData' => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['decoded'],
//            'formData'            => ToslaPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['expected'],
//            'gateway_url'         => 'https://ent.akodepos.com/api/Payment/threeDSecure/PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
//        ];
    }
}
