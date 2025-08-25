<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ToslaPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\Serializer\ToslaPosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\ToslaPos
 * @covers \Mews\Pos\Gateways\AbstractHttpGateway
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class ToslaPosTest extends TestCase
{
    public array $config;

    public CreditCardInterface $card;

    private ToslaPosAccount $account;

    /** @var ToslaPos */
    private PosInterface $pos;

    /** @var ToslaPosRequestDataMapper & MockObject */
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

    private ToslaPosRequestValueMapper $requestValueMapper;

    /** @var SerializerInterface & MockObject */
    private MockObject $serializerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => ToslaPos::class,
            'gateway_endpoints' => [
                'gateway_3d'      => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://ent.akodepos.com/api/Payment/threeDSecure',
            ],
        ];

        $this->account = AccountFactory::createToslaPosAccount(
            'tosla',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $this->requestValueMapper  = new ToslaPosRequestValueMapper();
        $this->requestMapperMock   = $this->createMock(ToslaPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClientInterface::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new ToslaPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->serializerMock,
            $this->eventDispatcherMock,
            $this->httpClientMock,
            $this->loggerMock,
        );
    }

    public function testInit(): void
    {
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
    }

    public function testGet3DGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL();

        $this->assertSame(
            $this->config['gateway_endpoints']['gateway_3d'],
            $actual
        );
    }

    public function testGet3DHostGatewayURL(): void
    {
        $sessionId = 'A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708';
        $actual    = $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST, $sessionId);

        $this->assertSame(
            $this->config['gateway_endpoints']['gateway_3d_host'] . '/' . $sessionId,
            $actual
        );
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $expectedResponse,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DPayPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider make3DPayPaymentWithoutHashCheckDataProvider
     */
    public function testMake3DPayPaymentWithoutHashCheck(
        array   $order,
        string  $txType,
        Request $request,
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
        $data    = ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'];
        $request = Request::create('', 'POST', $data);

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

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
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DHostPayment(
        array   $order,
        string  $txType,
        Request $request,
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DHostPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider make3DPayPaymentWithoutHashCheckDataProvider
     */
    public function testMake3DHostPaymentWithoutHashCheck(
        array   $order,
        string  $txType,
        Request $request,
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $pos->make3DHostPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $data    = ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'];
        $request = Request::create('', 'POST', $data);

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

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
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayment(array $order, string $txType, Request $request): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayment($request, $order, $txType, $this->card);
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
        array  $formData,
        string $gatewayUrl
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
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $decodedResponseData,
                $paymentModel,
                $txType,
                $gatewayUrl,
                $card
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame($actual, $formData);
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
     * @dataProvider registerFailResponseDataProvider
     */
    public function testGet3DFormDataRegisterPaymentFail(array $response): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['request-data'];
        $order       = ['order'];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $response,
            $order,
            PosInterface::MODEL_3D_PAY
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(\RuntimeException::class);
        $this->pos->get3DFormData($order, PosInterface::MODEL_3D_PAY, $txType, $this->card);
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_STATUS;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $account
        );

        $this->pos->status($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }


    /**
     * @dataProvider cancelDataProvider
     */
    public function testCancel(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_CANCEL,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->pos->cancel($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testRefund(
        array  $order,
        string $txType,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($this->pos->getAccount(), $order, $txType)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_REFUND,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->pos->refund($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }

    public function testHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->history([]);
    }

    /**
     * @dataProvider orderHistoryDataProvider
     */
    public function testOrderHistory(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_ORDER_HISTORY;

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->pos->orderHistory($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
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

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo',
            ],
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => null,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        $statusResponses = iterator_to_array(ToslaPosResponseDataMapperTest::statusResponseDataProvider());
        yield [
            'order'               => ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['order'],
            'requestData'         => ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode($statusResponses['success_pay']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => $statusResponses['success_pay']['responseData'],
            'mappedResponse'      => $statusResponses['success_pay']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function cancelDataProvider(): iterable
    {
        yield [
            'order'               => ToslaPosRequestDataMapperTest::cancelRequestDataProvider()[0]['order'],
            'requestData'         => ToslaPosRequestDataMapperTest::cancelRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::cancelRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(ToslaPosResponseDataMapperTest::cancelDataProvider()['success1']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => ToslaPosResponseDataMapperTest::cancelDataProvider()['success1']['responseData'],
            'mappedResponse'      => ToslaPosResponseDataMapperTest::cancelDataProvider()['success1']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function refundDataProvider(): iterable
    {
        yield [
            'order'               => ToslaPosRequestDataMapperTest::refundRequestDataProvider()[0]['order'],
            'txType'              => PosInterface::TX_TYPE_REFUND,
            'requestData'         => ToslaPosRequestDataMapperTest::refundRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::refundRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(ToslaPosResponseDataMapperTest::refundDataProvider()['success1']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => ToslaPosResponseDataMapperTest::refundDataProvider()['success1']['responseData'],
            'mappedResponse'      => ToslaPosResponseDataMapperTest::refundDataProvider()['success1']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        yield [
            'order'               => ToslaPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['order'],
            'requestData'         => ToslaPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(ToslaPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => ToslaPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['responseData'],
            'mappedResponse'      => ToslaPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_pay' => [
            'order'               => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['input'],
            'decodedResponseData' => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['decoded'],
            'formData'            => ToslaPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['expected'],
            'gateway_url'         => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
        ];

        yield '3d_host' => [
            'order'               => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_HOST,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => false,
            'requestData'         => ToslaPosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['input'],
            'decodedResponseData' => ToslaPosSerializerTest::decodeDataProvider()['payment_register']['decoded'],
            'formData'            => ToslaPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['expected'],
            'gateway_url'         => 'https://ent.akodepos.com/api/Payment/threeDSecure/PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
        ];
    }


    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'       => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['order'],
                'txType'      => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['txType'],
                'request'     => Request::create(
                    '',
                    'POST',
                    ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['paymentData']
                ),
                'expected'    => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['expectedData'],
                'is3DSuccess' => false,
                'isSuccess'   => false,
            ],
            'success'   => [
                'order'       => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['order'],
                'txType'      => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['txType'],
                'request'     => Request::create(
                    '',
                    'POST',
                    ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']
                ),
                'expected'    => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess' => true,
                'isSuccess'   => true,
            ],
        ];
    }

    public static function make3DPayPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            'success'   => [
                'order'       => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['order'],
                'txType'      => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['txType'],
                'request'     => Request::create(
                    '',
                    'POST',
                    ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']
                ),
                'expected'    => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess' => true,
                'isSuccess'   => true,
            ],
        ];
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
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

    public static function registerFailResponseDataProvider(): array
    {
        return [
            'merchant_not_found' => [
                'response' => [
                    'Code'            => 202,
                    'Message'         => 'Üye İşyeri Kullanıcısı Bulunamadı',
                    'ThreeDSessionId' => null,
                    'TransactionId'   => null,
                ],
            ],
        ];
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\ToslaPos ödeme altyapıda [pay] işlem tipi [3d_pay, 3d_host, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d]',
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
                $account
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
