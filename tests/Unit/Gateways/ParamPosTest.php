<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ParamPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ParamPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\Serializer\ParamPosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\ParamPos
 * @covers \Mews\Pos\Gateways\AbstractHttpGateway
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class ParamPosTest extends TestCase
{
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

    /** @var HttpClientStrategyInterface & MockObject */
    private MockObject $httpClientStrategyMock;

    /** @var HttpClientInterface & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    /** @var SerializerInterface & MockObject */
    private MockObject $serializerMock;

    /** @var ParamPosRequestValueMapper & MockObject */
    private ParamPosRequestValueMapper $requestValueMapperMock;

    private CreditCardInterface $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'param-pos',
            'class'             => ParamPos::class,
            'gateway_endpoints' => [],
        ];

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->requestValueMapperMock = $this->createMock(ParamPosRequestValueMapper::class);
        $this->requestMapperMock      = $this->createMock(ParamPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock         = $this->createMock(SerializerInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

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

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new ParamPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapperMock,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->serializerMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->requestValueMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '1000']);
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertFalse($this->pos->isTestMode());
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
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        array   $requestData,
        ?string $gatewayUrl,
        string  $encodedRequestData,
        string  $responseData,
        array   $decodedResponseData,
        $formData
    ): void {

        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $gatewayUrl,
                null,
                $decodedResponseData
            )
            ->willReturn($formData);

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
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(\RuntimeException::class);
        $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card);
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

    /**
     * @dataProvider make3DPaymentDataProvider
     */
    public function testMake3DPaymentWithoutHashCheck(
        array   $order,
        string  $txType,
        Request $request,
        array   $paymentResponse,
        array   $expectedResponse,
        bool    $is3DSuccess,
        bool    $isSuccess
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
            $this->serializerMock->expects(self::never())
                ->method('encode');
            $this->serializerMock->expects(self::never())
                ->method('decode');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $pos->make3DPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    /**
     * @dataProvider make3DPaymentDataForeignCurrencyProvider
     */
    public function testMake3DPaymentForeignCurrency(
        array   $order,
        string  $txType,
        Request $request,
        array   $expectedResponse,
        bool    $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->pos->getAccount(), $request->request->all())
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

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
        $this->eventDispatcherMock->expects(self::never())
            ->method('dispatch');

        $this->expectException(HashMismatchException::class);
        $this->pos->make3DPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $expectedResponse,
        bool    $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->pos->getAccount(), $request->request->all())
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DPayPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayPaymentWithoutHashCheck(
        array   $order,
        string  $txType,
        Request $request,
        array   $expectedResponse,
        bool    $isSuccess
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

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $pos->make3DPayPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $request = Request::create(
            '',
            'POST',
            ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']
        );
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->make3DPayPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testMake3DHostPayment(): void
    {
        $request = Request::create('', 'POST');

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DHostPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
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
            $this->account
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
            $this->account
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
            $this->account
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
            $this->account
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
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->history($order);
    }

    public function testOrderHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->orderHistory([]);
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
            $this->account
        );

        $this->pos->customQuery($requestData, $apiUrl);
    }

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx/abc',
            ],
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => null,
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData']
                ),
                'paymentResponse' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['order'],
                'txType'          => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['threeDResponseData']
                ),
                'paymentResponse' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['paymentData'],
                'expected'        => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'                      => [
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

    public static function make3DPaymentDataForeignCurrencyProvider(): array
    {
        return [
            'success_foreign_currency' => [
                'order'     => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success_foreign_currency']['order'],
                'txType'    => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success_foreign_currency']['txType'],
                'request'   => Request::create(
                    '',
                    'POST',
                    ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success_foreign_currency']['paymentData']
                ),
                'expected'  => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success_foreign_currency']['expectedData'],
                'isSuccess' => true,
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

    public static function historyRequestDataProvider(): array
    {
        $txTime = new \DateTimeImmutable();

        return [
            [
                'order' => [
                    'start_date' => $txTime->modify('-23 hour'),
                    'end_date'   => $txTime,
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
                'create_without_card'    => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\ParamPos ödeme altyapıda [pay] işlem tipi [3d, 3d_pay, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay_hosting].',
            ],
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'         => [
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

    public static function threeDFormDataFailResponseProvider(): iterable
    {
        $responseTestData = \iterator_to_array(ParamPosSerializerTest::decodeDataProvider());
        yield 'bad_request' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
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

        yield 'order_already_exist' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => 'encoded-request-data',
            'responseData'        => $responseTestData['3d_form_success']['input'],
            'decodedResponseData' => [
                "TP_WMD_UCDResponse" => [
                    "TP_WMD_UCDResult" => [
                        "Islem_ID"        => "0",
                        "Sonuc"           => "-400",
                        "Sonuc_Str"       => "Siparis_ID ye ait başarılı işlem mevcuttur. 124 Yeni Siparis_ID üreterek tekrar işlem deneyiniz.34/9/7",
                        "Banka_Sonuc_Kod" => "0",
                    ],
                ],
            ],
        ];

        yield 'hash_error' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => 'encoded-request-data',
            'responseData'        => '<response-data>',
            'decodedResponseData' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '0',
                        'Sonuc'           => '-102',
                        'Sonuc_Str'       => 'İşlem Hash geçersiz. Servise gelen Islem_Hash değeri:rvA0qAGEnAGZ8sfX4vk6AdSF/kI=2',
                        'Banka_Sonuc_Kod' => '0',
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        $responseTestData = \iterator_to_array(ParamPosSerializerTest::decodeDataProvider());
        yield '3d_secure' => [
            'order'               => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'gateway_url'         => null,
            'encodedRequestData'  => 'encoded-request-data',
            'responseData'        => $responseTestData['3d_form_success']['input'],
            'decodedResponseData' => $responseTestData['3d_form_success']['expected'],
            'formData'            => $responseTestData['3d_form_success']['expected']['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'],

        ];

        yield '3d_pay' => [
            'order'               => ParamPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay']['order'],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => ['request-data'],
            'gateway_url'         => null,
            'encodedRequestData'  => '<encoded-request-data>',
            'responseData'        => '<response-data>',
            'decodedResponseData' => ParamPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay']['extra_data'],
            'formData'            => ParamPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay']['expected'],
        ];
    }

    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'     => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['order'],
                'txType'    => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['txType'],
                'request'   => Request::create('', 'POST', ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['paymentData']),
                'expected'  => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['expectedData'],
                'isSuccess' => false,
            ],
            'success'   => [
                'order'     => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['order'],
                'txType'    => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['txType'],
                'request'   => Request::create('', 'POST', ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']),
                'expected'  => ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'isSuccess' => true,
            ],
        ];
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

        $mockMethod = $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(function (array $requestData) {
                    return $requestData['test-update-request-data-with-event'] === true;
                }),
                $order,
                $apiUrl,
                $account
            );
        if (isset($decodedResponse['soap:Fault'])) {
            $mockMethod->willThrowException(new \RuntimeException($decodedResponse['soap:Fault']['faultstring']));
        } else {
            $mockMethod->willReturn($decodedResponse);
        }


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
}
