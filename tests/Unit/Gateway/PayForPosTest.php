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
use Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayForPos::class)]
#[CoversClass(AbstractGateway::class)]
class PayForPosTest extends TestCase
{
    private PayForPosAccount $account;

    private array $config;

    /** @var PayForPos */
    private PosInterface $pos;

    /** @var PayForPosRequestDataMapper & MockObject */
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

    private CreditCardInterface $card;

    private PayForPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'QNBFinansbank-PayFor',
            'class'             => PayForPos::class,
            'gateway_endpoints' => [
                'gateway_3d'      => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
            ],
        ];

        $this->account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            '12345678'
        );

        $this->requestValueMapper     = new PayForPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(PayForPosRequestDataMapper::class);
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
        $this->assertSame($this->config['gateway_endpoints']['gateway_3d_host'], $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST));
        $this->assertSame($this->config['gateway_endpoints']['gateway_3d'], $this->pos->get3DGatewayURL());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[TestWith([true, '3d', 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx'])]
    #[TestWith([false, '3d_host', 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx'])]
    public function testGet3DFormData(
        bool   $isWithCard,
        string $paymentModel,
        string $gatewayUrl
    ): void {
        $card   = $isWithCard ? $this->card : null;
        $order  = ['id' => '124'];
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

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
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame(['formData'], $actual);
    }

    #[DataProvider('get3DFormDataHtmlFormatDataProvider')]
    public function testGet3DFormDataHtmlFormat(string $paymentModel, string $txType): void
    {
        $order        = ['id' => '124'];
        $requestData  = ['MerchantId' => '085300000009704', 'hash' => 'abc'];
        $htmlResponse = '<html><body>3d-form</body></html>';

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->account, $order, $paymentModel, $txType, $this->card)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel, $requestData, $order)
            ->willReturn($htmlResponse);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card, false, PosInterface::FORM_FORMAT_HTML);

        $this->assertSame($htmlResponse, $actual);
    }

    public function testGet3DFormDataHtmlFormatWithEventModifiedRequestData(): void
    {
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['MerchantId' => '085300000009704', 'hash' => 'abc'];
        $modifiedData = ['MerchantId' => '085300000009704', 'hash' => 'xyz', 'extra' => '1'];
        $htmlResponse = '<html><body>3d-form</body></html>';

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->account, $order, $paymentModel, $txType, $this->card)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (RequestDataPreparedEvent $event) use ($modifiedData): RequestDataPreparedEvent {
                $event->setRequestData($modifiedData);

                return $event;
            });

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel, $modifiedData, $order)
            ->willReturn($htmlResponse);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card, false, PosInterface::FORM_FORMAT_HTML);

        $this->assertSame($htmlResponse, $actual);
    }

    public function testGet3DFormDataHtmlFormatEmptyResponse(): void
    {
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['MerchantId' => '085300000009704'];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->account, $order, $paymentModel, $txType, $this->card)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $paymentModel, $requestData, $order)
            ->willReturn('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('3D form verisi oluşturulamadı');

        $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card, false, PosInterface::FORM_FORMAT_HTML);
    }

    #[DataProvider('threeDFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        bool    $createWithoutCard,
        string  $expectedExceptionClass,
        string  $expectedExceptionMessage,
        ?string $formFormat = null
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);

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
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = [
            'RequestGuid'     => '1000000081255931',
            'TransactionDate' => '31.10.2022 22:34:18',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
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
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], $txType, null, $gatewayResponseData);
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment(): void
    {
        $gatewayResponseData = ['$responseData'];
        $order               = ['id' => '123'];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $gatewayResponseData = ['$responseData'];
        $order               = ['id' => '123'];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPayResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);
    }

    /**
     * @return void
     */
    public function testMake3DHostPayment(): void
    {
        $gatewayResponseData = ['$responseData'];
        $order               = ['id' => '123'];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $gatewayResponseData = ['$responseData'];
        $order               = ['id' => '123'];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DHostResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);
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
            $account
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
            $account
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
            $account
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
            $account
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
            $account
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
            'auth_fail'                  => [
                'order'           => [],
                'txType'          => 'pre',
                'request'         => [
                    'RequestGuid' => '1000000081255934',
                ],
                'paymentResponse' => [],
                'expected'        => [
                    'order_id'         => '202210317565',
                    'proc_return_code' => 'V034',
                    'status'           => 'declined',
                    'error_code'       => 'V034',
                    'error_message'    => '3D Kullanıcı Doğrulama Adımı Başarısız',
                    'md_error_code'    => 'V034',
                    'md_error_message' => '3D Kullanıcı Doğrulama Adımı Başarısız',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'order_number_already_exist' => [
                'order'           => [],
                'txType'          => 'pay',
                'request'         => [
                    'RequestGuid'     => '0',
                    'TransactionDate' => '17.03.2024 17:47:28',
                    'MbrId'           => '5',
                    'OkUrl'           => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'         => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrMsg'          => 'Verilen sipariş no önceden kullanılmıştır.',
                    'ProcReturnCode'  => '101310',
                    'ReturnUrl'       => 'http://localhost/finansbank-payfor/3d/response.php',

                ],
                'paymentResponse' => [],
                'expected'        => [
                    'order_id'         => '202403173F72',
                    'proc_return_code' => '101310',
                    'status'           => 'declined',
                    'error_code'       => '101310',
                    'error_message'    => 'Verilen sipariş no önceden kullanılmıştır.',
                    'md_error_code'    => '101310',
                    'md_error_message' => 'Verilen sipariş no önceden kullanılmıştır.',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'                    => [
                'order'           => [],
                'txType'          => 'pre',
                'request'         => [
                    'RequestGuid'     => '1000000081255931',
                    'TransactionDate' => '31.10.2022 22:34:18',
                    'MbrId'           => '5',
                    'OkUrl'           => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'         => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrMsg'          => '3D Kullanıcı Doğrulama Adımı Başarılı',
                    'ProcReturnCode'  => 'V033',
                    'ReturnUrl'       => 'http://localhost/finansbank-payfor/3d/response.php',
                ],
                'paymentResponse' => [
                    'AuthCode'       => 'S37397',
                    'HostRefNum'     => '230422098249',
                    'ProcReturnCode' => '00',
                    'TransId'        => '20221031CFD0',
                    'ErrMsg'         => 'Onaylandı',
                    'CardHolderName' => 'John Doe',
                ],
                'expected'        => [
                    'transaction_id'    => '20221031CFD0',
                    'transaction_type'  => 'pay',
                    'status'            => 'approved',
                    'error_code'        => null,
                    'error_message'     => null,
                    'payment_model'     => '3d',
                    'installment_count' => 0,
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            'success' => [
                'order'           => [],
                'txType'          => 'pre',
                'request'         => [
                    'RequestGuid'     => '1000000081255931',
                    'TransactionDate' => '31.10.2022 22:34:18',
                    'MbrId'           => '5',
                    'OkUrl'           => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'         => 'http://localhost/finansbank-payfor/3d/response.php',
                    'ErrMsg'          => '3D Kullanıcı Doğrulama Adımı Başarılı',
                ],
                'paymentResponse' => [
                    'AuthCode'       => 'S37397',
                    'HostRefNum'     => '230422098249',
                    'ProcReturnCode' => '00',
                    'TransId'        => '20221031CFD0',
                    'ErrMsg'         => 'Onaylandı',
                    'CardHolderName' => 'John Doe',
                ],
                'expected'        => [
                    'transaction_id'   => '20221031CFD0',
                    'transaction_type' => 'pay',
                    'status'           => 'approved',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
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

    public static function get3DFormDataHtmlFormatDataProvider(): array
    {
        return [
            '3d_secure' => [PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_AUTH],
            '3d_pay'    => [PosInterface::MODEL_3D_PAY, PosInterface::TX_TYPE_PAY_AUTH],
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
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\PayForPos ödeme altyapıda [pay] işlem tipi [3d, 3d_pay, 3d_host, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay_hosting].',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
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

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new PayForPos(
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
                $this->callback(function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                    $updatedRequestDataPreparedEvent = $dispatchedEvent;

                    return $this->pos::class === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $requestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel();
                })
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
