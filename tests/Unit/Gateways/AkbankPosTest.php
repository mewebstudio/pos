<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\AkbankPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\AkbankPos
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class AkbankPosTest extends TestCase
{
    use HttpClientTestTrait;

    public array $config;

    public CreditCardInterface $card;

    private AkbankPosAccount $account;

    /** @var AkbankPos */
    private PosInterface $pos;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => AkbankPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgateway.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgateway.akbank.com/payhosting',
            ],
        ];

        $this->account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
            PosInterface::LANG_TR
        );

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

        $this->pos = new AkbankPos(
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

        $this->card = CreditCardFactory::create('5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testInit(): void
    {
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiURL(?string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->pos->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider getApiUrlExceptionDataProvider
     */
    public function testGetApiURLException(?string $txType, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);

        $this->pos->getApiURL($txType);
    }

    public function testGet3DGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL();

        $this->assertSame(
            'https://virtualpospaymentgateway.akbank.com/securepay',
            $actual
        );
    }

    public function testGet3DHostGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST);

        $this->assertSame(
            'https://virtualpospaymentgateway.akbank.com/payhosting',
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

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $request = Request::create(
            '',
            'POST',
            AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']
        );
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->make3DPayPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider make3DHostPaymentDataProvider
     */
    public function testMake3DHostPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $expectedResponse,
        bool    $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DHostPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $request = Request::create(
            '',
            'POST',
            AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['paymentData']
        );
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->make3DHostPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
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
                'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
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
        $request = Request::create(
            '',
            'POST',
            AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
        );
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($request->request->all())
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn(true);

        $this->expectException(HashMismatchException::class);

        $this->pos->make3DPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        array  $formData,
        string $gatewayUrl
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
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
            'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
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

        $requestData = ['createNonSecurePaymentRequestData'];
        $this->configureClientResponse(
            $txType,
            $apiUrl,
            $requestData,
            'request-body',
            $apiUrl,
            ['paymentResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPayment($order, $card, $txType);
    }

    /**
     * @dataProvider makeRegularPaymentDataProvider
     */
    public function testMakeRegularPaymentBadRequest(array $order, string $txType, string $apiUrl): void
    {
        $account     = $this->pos->getAccount();
        $card        = $this->card;
        $requestData = ['createNonSecurePaymentRequestData'];
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $apiUrl,
            $requestData,
            'request-body',
            $apiUrl,
            ['code' => 123, 'message' => 'error'],
            $order,
            PosInterface::MODEL_NON_SECURE,
            400
        );

        $this->expectException(\RuntimeException::class);
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

        $this->configureClientResponse(
            $txType,
            $apiUrl,
            $requestData,
            'request-body',
            $apiUrl,
            ['paymentResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->makeRegularPostPayment($order);
    }

    public function testStatusRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->status([]);
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

        $this->configureClientResponse(
            $txType,
            $apiUrl,
            $requestData,
            'request-body',
            $apiUrl,
            ['decodedResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->cancel($order);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testRefundRequest(array $order, string $txType, string $apiUrl): void
    {
        $account     = $this->pos->getAccount();
        $requestData = ['createRefundRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $apiUrl,
            ['createRefundRequestData'],
            'request-body',
            $apiUrl,
            ['decodedResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with(['decodedResponse'])
            ->willReturn(['result']);

        $this->pos->refund($order);
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
                'api_url'          => 'https://apipre.akbank.com/api/v1/payment/virtualpos/xxxx',
                'expected_api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/xxxx',
            ],
            [
                'requestData'      => [
                    'id' => '2020110828BC',
                ],
                'api_url'          => null,
                'expected_api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/portal/report/transaction',
            ],
        ];
    }

    public static function getApiUrlExceptionDataProvider(): array
    {
        return [
            [
                'txType'          => null,
                'exception_class' => \InvalidArgumentException::class,
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
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
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
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
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
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund'    => [
                'order'   => [
                    'id'     => '2020110828BC',
                    'amount' => 5,
                ],
                'tx_type' => PosInterface::TX_TYPE_REFUND,
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            'partial_refund' => [
                'order'   => [
                    'id'           => '2020110828BC',
                    'amount'       => 5,
                    'order_amount' => 10,
                ],
                'tx_type' => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData']
                ),
                'paymentResponse' => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['order'],
                'txType'          => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['threeDResponseData']
                ),
                'paymentResponse' => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['paymentData'],
                'expected'        => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
                ),
                'paymentResponse' => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        yield [
            'order'               => AkbankPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['order'],
            'requestData'         => AkbankPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkbankPosRequestDataMapperTest::orderHistoryRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(AkbankPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => AkbankPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['responseData'],
            'mappedResponse'      => AkbankPosResponseDataMapperTest::orderHistoryDataProvider()['success_only_payment_transaction']['expectedData'],
            'isSuccess'           => true,
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
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_host_with_card'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Kart bilgileri ile form verisi oluşturmak icin [3d_host] ödeme modeli kullanmayınız! Yerine [3d, 3d_pay, regular] ödeme model(ler)ini kullanınız.',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\AkbankPos ödeme altyapıda [pay] işlem tipi [3d, 3d_pay, 3d_host, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay_hosting].',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_HISTORY,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_host' => [
            'order'        => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_host_form_data']['order'],
            'paymentModel' => PosInterface::MODEL_3D_HOST,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => false,
            'formData'     => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_host_form_data']['expected'],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/payhosting',
        ];

        yield '3d_pay' => [
            'order'        => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['order'],
            'paymentModel' => PosInterface::MODEL_3D_PAY,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => true,
            'formData'     => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['expected'],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/securepay',
        ];

        yield '3d_secure' => [
            'order'        => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_form_data']['order'],
            'paymentModel' => PosInterface::MODEL_3D_SECURE,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => true,
            'formData'     => AkbankPosRequestDataMapperTest::threeDFormDataProvider()['3d_form_data']['expected'],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/securepay',
        ];
    }


    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'     => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['order'],
                'txType'    => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['txType'],
                'request'   => Request::create('', 'POST', AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['paymentData']),
                'expected'  => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['expectedData'],
                'isSuccess' => false,
            ],
            'success'   => [
                'order'     => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['order'],
                'txType'    => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['txType'],
                'request'   => Request::create('', 'POST', AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']),
                'expected'  => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'isSuccess' => true,
            ],
        ];
    }

    public static function make3DHostPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'     => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['auth_fail']['order'],
                'txType'    => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['auth_fail']['txType'],
                'request'   => Request::create(
                    '',
                    'POST',
                    AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['auth_fail']['paymentData']
                ),
                'expected'  => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['auth_fail']['expectedData'],
                'isSuccess' => false,
            ],
            'success'   => [
                'order'     => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['order'],
                'txType'    => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['txType'],
                'request'   => Request::create(
                    '',
                    'POST',
                    AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['paymentData']
                ),
                'expected'  => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['expectedData'],
                'isSuccess' => true,
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'order'   => [
                    'batch_num' => 123,
                ],
                'api_url' => 'https://apipre.akbank.com/api/v1/payment/virtualpos/portal/report/transaction',
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
        string $paymentModel,
        ?int   $statusCode = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->cryptMock->expects(self::once())
            ->method('hashString')
            ->with($encodedRequestData, $this->account->getStoreKey())
            ->willReturn('request-body-hash');

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
                    'auth-hash'    => 'request-body-hash',
                ],
                'body'    => $encodedRequestData,
            ],
            $statusCode
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
