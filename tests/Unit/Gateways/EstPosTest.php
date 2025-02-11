<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\EstPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\EstPos
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class EstPosTest extends TestCase
{
    use HttpClientTestTrait;

    private EstPosAccount $account;

    /** @var EstPos */
    private PosInterface $pos;

    /** @var array<string, mixed> */
    private array $config;

    /** @var RequestDataMapperInterface & MockObject */
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

    private CreditCardInterface $card;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => EstPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ];

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestMapperMock   = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new EstPos(
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
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
    }

    /**
     * @testWith [true]
     * [false]
     */
    public function testGet3DFormData(
        bool $isWithCard
    ): void {
        $card         = $isWithCard ? $this->card : null;
        $paymentModel = $isWithCard ? PosInterface::MODEL_3D_SECURE : PosInterface::MODEL_3D_HOST;
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_PRE_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                $card
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

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
     * @return void
     */
    public function testMake3DHostPaymentSuccess(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $testData = EstPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1'];
        $request  = Request::create('', 'POST', $testData['paymentData']);
        $order    = $testData['order'];
        $txType   = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($request->request->all(), $txType, $order)
            ->willReturn($testData['expectedData']);

        $pos = $this->pos;

        $pos->make3DHostPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $data = EstPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['paymentData'];
        $request = Request::create('', 'POST', $data);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DHostResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->make3DHostPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentSuccess(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $testData = EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1'];
        $request  = Request::create('', 'POST', $testData['paymentData']);
        $order    = $testData['order'];
        $txType   = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($request->request->all(), $txType, $order)
            ->willReturn($testData['expectedData']);

        $pos = $this->pos;

        $pos->make3DPayPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $data = EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'];
        $request = Request::create('', 'POST', $data);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPayResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->make3DPayPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = ['createStatusRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            $requestData,
            'request-body',
            'response-body',
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->status($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->history([]);
    }

    /**
     * @dataProvider orderHistoryDataProvider
     */
    public function testOrderHistory(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_ORDER_HISTORY;
        $requestData = ['createOrderHistoryRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            $requestData,
            'request-body',
            'response-body',
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->orderHistory($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testCancel(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_CANCEL;
        $requestData = ['createCancelRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            $requestData,
            'request-body',
            'response-body',
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->cancel($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testRefund(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_REFUND;
        $requestData = ['createRefundRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            $requestData,
            'request-body',
            'response-body',
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->refund($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
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
                'https://entegrasyon.asseco-see.com.tr/fim/api',
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
        $data = EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['threeDResponseData'];
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

        $decodedResponse = ['paymentResponse'];
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

        $decodedResponse = ['paymentResponse'];
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
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPostPayment($order);
    }

    /**
     * @dataProvider customQueryRequestDataProvider
     */
    public function testCustomQueryRequest(array $requestData, ?string $apiUrl, string $expectedApiUrl): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_CUSTOM_QUERY;

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
                'api_url'          => 'https://entegrasyon.asseco-see.com.tr/fim/api/xxxx',
                'expected_api_url' => 'https://entegrasyon.asseco-see.com.tr/fim/api/xxxx',
            ],
            [
                'requestData'      => [
                    'id' => '2020110828BC',
                ],
                'api_url'          => null,
                'expected_api_url' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData']
                ),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['threeDResponseData']
                ),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
                ),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return [
            'fail_1'    => [
                'bank_response' => EstPosResponseDataMapperTest::cancelTestDataProvider()['fail_order_not_found_1']['responseData'],
                'expected_data' => EstPosResponseDataMapperTest::cancelTestDataProvider()['fail_order_not_found_1']['expectedData'],
                'isSuccess'     => false,
            ],
            'success_1' => [
                'bank_response' => EstPosResponseDataMapperTest::cancelTestDataProvider()['success1']['responseData'],
                'expected_data' => EstPosResponseDataMapperTest::cancelTestDataProvider()['success1']['expectedData'],
                'isSuccess'     => true,
            ],
        ];
    }

    public static function refundDataProvider(): array
    {
        return [
            'fail_1' => [
                'bank_response' => EstPosResponseDataMapperTest::refundTestDataProvider()['fail1']['responseData'],
                'expected_data' => EstPosResponseDataMapperTest::refundTestDataProvider()['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'bank_response' => EstPosResponseDataMapperTest::statusTestDataProvider()['fail1']['responseData'],
            'expected_data' => EstPosResponseDataMapperTest::statusTestDataProvider()['fail1']['expectedData'],
            'isSuccess'     => false,
        ];
        yield [
            'bank_response' => EstPosResponseDataMapperTest::statusTestDataProvider()['success1']['responseData'],
            'expected_data' => EstPosResponseDataMapperTest::statusTestDataProvider()['success1']['expectedData'],
            'isSuccess'     => true,
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        yield [
            'bank_response' => EstPosResponseDataMapperTest::orderHistoryTestDataProvider()['success_cancel_success_refund_fail']['responseData'],
            'expected_data' => EstPosResponseDataMapperTest::orderHistoryTestDataProvider()['success_cancel_success_refund_fail']['expectedData'],
            'isSuccess'     => true,
        ];
        yield [
            'bank_response' => EstPosResponseDataMapperTest::orderHistoryTestDataProvider()['fail1']['responseData'],
            'expected_data' => EstPosResponseDataMapperTest::orderHistoryTestDataProvider()['fail1']['expectedData'],
            'isSuccess'     => false,
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
                'api_url' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'api_url' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
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
                'api_url' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
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
                'api_url' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
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
                'api_url' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
        ];
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            '3d_secure_without_card' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_pay_without_card'    => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'non_payment_tx_type'    => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'      => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
        ];
    }

    private function configureClientResponse(
        string $txType,
        string $apiUrl,
        array  $requestData,
        string $encodedRequestData,
        string $responseContent,
        array  $decodedResponse,
        array  $order,
        string $paymentModel
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($this->logicalAnd($this->arrayHasKey('test-update-request-data-with-event')), $txType)
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
                'body' => $encodedRequestData,
            ],
        );

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
                $updatedRequestData = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
