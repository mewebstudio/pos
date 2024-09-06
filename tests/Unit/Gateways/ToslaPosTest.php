<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ToslaPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use Mews\Pos\Tests\Unit\Serializer\ToslaPosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\ToslaPos
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class ToslaPosTest extends TestCase
{
    use HttpClientTestTrait;

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

    /** @var HttpClient & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    /** @var SerializerInterface & MockObject */
    private MockObject $serializerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => ToslaPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://ent.akodepos.com/api/Payment',
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

        $this->requestMapperMock   = $this->createMock(ToslaPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new ToslaPos(
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

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testInit(): void
    {
        $this->assertEquals($this->config, $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiURL(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->pos->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGet3DGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL();

        $this->assertSame(
            'https://ent.akodepos.com/api/Payment/ProcessCardForm',
            $actual
        );
    }

    public function testGet3DHostGatewayURL(): void
    {
        $sessionId = 'A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708';
        $actual    = $this->pos->get3DHostGatewayURL($sessionId);

        $this->assertSame(
            'https://ent.akodepos.com/api/Payment/threeDSecure/A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708',
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
    ): void
    {
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
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DHostPayment(
        array   $order,
        string  $txType,
        Request $request,
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DHostPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
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
    ): void
    {

        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://ent.akodepos.com/api/Payment/threeDPayment',
            $requestData,
            $encodedRequestData,
            $responseData,
            $decodedResponseData,
            $order,
            $paymentModel
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
    ): void
    {
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
            'https://ent.akodepos.com/api/Payment/inquiry',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    ): void
    {
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
            'https://ent.akodepos.com/api/Payment/void',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    ): void
    {
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
            'https://ent.akodepos.com/api/Payment/refund',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
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
    ): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_ORDER_HISTORY;

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
            'https://ent.akodepos.com/api/Payment/history',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->pos->orderHistory($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'order'               => ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['order'],
            'requestData'         => ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(ToslaPosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(ToslaPosResponseDataMapperTest::statusResponseDataProvider()['success_pay']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => ToslaPosResponseDataMapperTest::statusResponseDataProvider()['success_pay']['responseData'],
            'mappedResponse'      => ToslaPosResponseDataMapperTest::statusResponseDataProvider()['success_pay']['expectedData'],
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
            'decodedResponseData' => ToslaPosResponseDataMapperTest::statusResponseDataProvider()['success_pay']['responseData'],
            'mappedResponse'      => ToslaPosResponseDataMapperTest::statusResponseDataProvider()['success_pay']['expectedData'],
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
                'request'     => Request::create('', 'POST', ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['paymentData']),
                'expected'    => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['expectedData'],
                'is3DSuccess' => false,
                'isSuccess'   => false,
            ],
            'success'   => [
                'order'       => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['order'],
                'txType'      => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['txType'],
                'request'     => Request::create('', 'POST', ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']),
                'expected'    => ToslaPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess' => true,
                'isSuccess'   => true,
            ],
        ];
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


    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/Payment',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/postAuth',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/inquiry',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/void',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/refund',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/refund',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_ORDER_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/history',
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
                'api_url' => 'https://ent.akodepos.com/api/Payment/Payment',
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
                'api_url' => 'https://ent.akodepos.com/api/Payment/postAuth',
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
                'api_url' => 'https://ent.akodepos.com/api/Payment/inquiry',
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
                'api_url' => 'https://ent.akodepos.com/api/Payment/void',
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
                'api_url' => 'https://ent.akodepos.com/api/Payment/refund',
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
    ): void
    {
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
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => $encodedRequestData,
            ],
        );

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent) {
                    $updatedRequestDataPreparedEvent = $dispatchedEvent;

                    return get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $requestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel();
                }
                )))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent) {
                $updatedRequestData = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
