<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(AkbankPos::class)]
#[CoversClass(AbstractGateway::class)]
class AkbankPosTest extends TestCase
{
    private array $config;

    private CreditCardInterface $card;

    private AkbankPosAccount $account;

    /** @var AkbankPos */
    private PosInterface $pos;

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

    private AkbankPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => AkbankPos::class,
            'gateway_endpoints' => [
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

        $this->requestValueMapper     = new AkbankPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config, $this->account);

        $this->card = CreditCardFactory::create('5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testInit(): void
    {
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    public function testInitOnTestModeLogsWarning(): void
    {
        $config = $this->config;
        $config['gateway_configs']['test_mode'] = true;

        $this->requestMapperMock->expects(self::once())
            ->method('setTestMode')
            ->with(true);

        $this->loggerMock->expects(self::once())
            ->method('warning')
            ->with(
                'test_mode gateway ayarı bu gateway için istek verisini etkilemez; '
                . 'test ve production ortamı ayrımı config\'deki endpoint URL\'leri ile yapılır',
                ['gateway' => AkbankPos::class]
            );

        $pos = $this->createGateway($config);

        $this->assertTrue($pos->isTestMode());
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
        $actual = $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST);

        $this->assertSame(
            $this->config['gateway_endpoints']['gateway_3d_host'],
            $actual
        );
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
        bool   $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->pos->getAccount(), $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
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

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $gatewayResponseData = [
            'txnCode'      => '1000',
            'responseCode' => 'VPS-0000',
            'orderId'      => '2024041811DA',
        ];
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
    }

    #[DataProvider('make3DHostPaymentDataProvider')]
    public function testMake3DHostPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
        bool   $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }


    #[DataProvider('make3DHostPaymentDataProvider')]
    public function testMake3DHostPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
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

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $gatewayResponseData = [
            'txnCode'      => '1000',
            'responseCode' => 'VPS-0000',
            'orderId'      => '2024041898FD',
        ];
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
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
        $gatewayResponseData = [
            'txnCode'      => '3001',
            'responseCode' => 'VPS-0000',
            'orderId'      => '20240418BA6C',
        ];
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn(true);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
    }

    #[DataProvider('threeDFormDataProvider')]
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

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame($actual, $formData);
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

    #[DataProvider('orderHistoryDataProvider')]
    public function testOrderHistory(
        array $order,
        array $requestData,
        array $decodedResponse,
        array $mappedResponse,
        bool  $isSuccess
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
            PosInterface::MODEL_NON_SECURE
        );

        $result = $this->pos->orderHistory($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $this->assertSame($result, $mappedResponse);
    }

    #[DataProvider('makeRegularPaymentDataProvider')]
    public function testMakeRegularPayment(array $order, string $txType): void
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
            $requestData,
            ['paymentResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $card);
    }

    #[DataProvider('makeRegularPaymentDataProvider')]
    public function testMakeRegularPaymentBadRequest(array $order, string $txType): void
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
            $requestData,
            ['code' => 123, 'message' => 'error'],
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            400
        );

        $this->expectException(RuntimeException::class);
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

        $this->configureClientResponse(
            $txType,
            $requestData,
            ['paymentResponse'],
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType);
    }

    public function testStatusRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->status([]);
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

        $this->configureClientResponse(
            $txType,
            $requestData,
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

    #[DataProvider('refundRequestDataProvider')]
    public function testRefundRequest(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
        $requestData = ['createRefundRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            ['createRefundRequestData'],
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
            'full_refund'    => [
                'order'   => [
                    'id'     => '2020110828BC',
                    'amount' => 5,
                ],
                'tx_type' => PosInterface::TX_TYPE_REFUND,
            ],
            'partial_refund' => [
                'order'   => [
                    'id'           => '2020110828BC',
                    'amount'       => 5,
                    'order_amount' => 10,
                ],
                'tx_type' => PosInterface::TX_TYPE_REFUND_PARTIAL,
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'               => ['id' => 'order-3d-fail'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240418D4A6', 'responseCode' => 'VPS-1279'],
                'paymentResponse'     => [],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => false,
                'isSuccess'           => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'               => ['id' => 'order-3d-pay-fail'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240420D268', 'responseCode' => 'VPS-0000'],
                'paymentResponse'     => ['responseCode' => 'VPS-1005'],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => ['id' => 'order-3d-success'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240418BA6C', 'responseCode' => 'VPS-0000'],
                'paymentResponse'     => ['responseCode' => 'VPS-0000'],
                'expected'            => ['status' => 'approved'],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            '3d_auth_success_payment_fail' => [
                'order'               => ['id' => 'order-3d-pay-fail-nohash'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240420D268', 'responseCode' => 'VPS-0000'],
                'paymentResponse'     => ['responseCode' => 'VPS-1005'],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => ['id' => 'order-3d-success-nohash'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240418BA6C', 'responseCode' => 'VPS-0000'],
                'paymentResponse'     => ['responseCode' => 'VPS-0000'],
                'expected'            => ['status' => 'approved'],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        yield [
            'order'           => [
                'id' => '2020110828BC',
            ],
            'requestData'     => [
                'txnCode' => '1010',
                'order'   => ['orderId' => '2020110828BC'],
            ],
            'decodedResponse' => [
                'txnCode'      => '1010',
                'responseCode' => 'VPS-0000',
            ],
            'mappedResponse'  => [
                'status' => 'approved',
            ],
            'isSuccess'       => true,
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
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_host_with_card'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Kart bilgileri ile form verisi oluşturmak icin [3d_host] ödeme modeli kullanmayınız! Yerine [3d, 3d_pay, regular] ödeme model(ler)ini kullanınız.',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => true,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\AkbankPos ödeme altyapıda [pay] işlem tipi [3d, 3d_pay, 3d_host, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay_hosting].',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosQueryInterface::QUERY_TYPE_HISTORY,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'unsupported_form_format'   => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_host' => [
            'order'        => [
                'id'          => '2020110828BC',
                'amount'      => 10,
                'ip'          => '127.0.0.1',
                'installment' => 0,
                'currency'    => 'TRY',
                'success_url' => 'http:://localhost/success',
                'fail_url'    => 'http:://localhost/fail',
            ],
            'paymentModel' => PosInterface::MODEL_3D_HOST,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => false,
            'formData'     => [
                'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                'method'  => 'POST',
                'inputs'  => [
                    'paymentModel'   => '3D_PAY_HOSTING',
                    'txnCode'        => '3000',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'hash'           => 'hash-123',
                ],
            ],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/payhosting',
        ];

        yield '3d_pay' => [
            'order'        => [
                'id'          => '2020110828BC',
                'amount'      => 1.1,
                'ip'          => '127.0.0.1',
                'installment' => 0,
                'currency'    => 'TRY',
                'success_url' => 'http:://localhost/success',
                'fail_url'    => 'http:://localhost/fail',
            ],
            'paymentModel' => PosInterface::MODEL_3D_PAY,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => true,
            'formData'     => [
                'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'method'  => 'POST',
                'inputs'  => [
                    'paymentModel'   => '3D_PAY',
                    'txnCode'        => '3000',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'orderId'        => '2020110828BC',
                    'hash'           => 'hash-123',
                ],
            ],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/securepay',
        ];

        yield '3d_secure' => [
            'order'        => [
                'id'          => '2020110828BC',
                'amount'      => 1.1,
                'ip'          => '127.0.0.1',
                'installment' => 0,
                'currency'    => 'TRY',
                'success_url' => 'http:://localhost/success',
                'fail_url'    => 'http:://localhost/fail',
            ],
            'paymentModel' => PosInterface::MODEL_3D_SECURE,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'   => true,
            'formData'     => [
                'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'method'  => 'POST',
                'inputs'  => [
                    'paymentModel'   => '3D',
                    'txnCode'        => '3000',
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    'orderId'        => '2020110828BC',
                    'hash'           => 'hash-123',
                ],
            ],
            'gateway_url'  => 'https://virtualpospaymentgateway.akbank.com/securepay',
        ];
    }


    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'               => ['id' => 'order-3dpay-fail'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '202404180331', 'responseCode' => 'VPS-1279'],
                'expected'            => ['status' => 'declined'],
                'isSuccess'           => false,
            ],
            'success'   => [
                'order'               => ['id' => 'order-3dpay-success'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '2024041811DA', 'responseCode' => 'VPS-0000'],
                'expected'            => ['status' => 'approved'],
                'isSuccess'           => true,
            ],
        ];
    }

    public static function make3DHostPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'               => ['id' => 'order-3dhost-fail'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '20240418452F', 'responseCode' => 'VPS-1279'],
                'expected'            => ['status' => 'declined'],
                'isSuccess'           => false,
            ],
            'success'   => [
                'order'               => ['id' => 'order-3dhost-success'],
                'txType'              => 'pay',
                'gatewayResponseData' => ['orderId' => '2024041898FD', 'responseCode' => 'VPS-0000'],
                'expected'            => ['status' => 'approved'],
                'isSuccess'           => true,
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new AkbankPos(
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
        string  $txType,
        array   $requestData,
        array   $decodedResponse,
        array   $order,
        string  $paymentModel,
        ?string $apiUrl = null,
        ?int    $statusCode = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($txType, $paymentModel)
            ->willReturn($this->httpClientMock);

        $invocationMocker = $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(fn (array $requestData): bool => $requestData['test-update-request-data-with-event'] === true),
                $order,
                $apiUrl,
                $this->account
            );
        if ($statusCode >= 400) {
            $invocationMocker->willThrowException(new RuntimeException());
        } else {
            $invocationMocker->willReturn($decodedResponse);
        }

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
