<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\InterPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\InterPos
 */
class InterPosTest extends TestCase
{
    use HttpClientTestTrait;

    private InterPosAccount $account;

    /** @var InterPos */
    private PosInterface $pos;

    private CreditCardInterface $card;

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

    private array $config;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'  => 'DenizBank-InterPos',
            'class' => InterPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ];

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
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

        $this->pos = new InterPos(
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
        $card = $isWithCard ? $this->card : null;
        $order = ['id' => '124'];
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                $card
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

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
        bool    $checkHash,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void {
        if ($checkHash) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->willReturn(true);
        }

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
                $this->config['gateway_endpoints']['payment_api'],
                [
                    'body' => 'request-body',
                ],
            );

            $this->serializerMock->expects(self::once())
                ->method('encode')
                ->with($create3DPaymentRequestData, $txType)
                ->willReturn('request-body');
            $this->serializerMock->expects(self::once())
                ->method('decode')
                ->with('response-body', $txType)
                ->willReturn($paymentResponse);

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
        }

        $this->pos->make3DPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment(): void
    {
        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $responseData = ['$responseData'];
        $request  = Request::create('', 'POST', $responseData);
        $order    = ['id' => '123'];
        $txType   = PosInterface::TX_TYPE_PAY_AUTH;

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
        $request  = Request::create('', 'POST', $responseData);
        $order    = ['id' => '123'];
        $txType   = PosInterface::TX_TYPE_PAY_AUTH;

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

    public function testHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->history([]);
    }

    public function testOrderHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->orderHistory([]);
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
                'form_params' => ['request-body'],
            ]
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createNonSecurePaymentRequestData'], $txType)
            ->willReturn(['request-body']);

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
            ->willReturn(['request-body']);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'form_params' => ['request-body'],
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
        $txType = PosInterface::TX_TYPE_STATUS;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn(['createStatusRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createStatusRequestData'], $txType)
            ->willReturn(['request-body']);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'form_params' => ['request-body'],
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
        $txType = PosInterface::TX_TYPE_CANCEL;

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn(['createCancelRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createCancelRequestData'], $txType)
            ->willReturn(['request-body']);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'form_params' => ['request-body'],
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
        $txType = PosInterface::TX_TYPE_REFUND;

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order)
            ->willReturn(['createRefundRequestData']);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['createRefundRequestData'], $txType)
            ->willReturn(['request-body']);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            $apiUrl,
            [
                'form_params' => ['request-body'],
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

    public static function make3DPaymentDataProvider(): array
    {
        return [
            '3d_fail' => [
                'order'           => InterPosResponseDataMapperTest::threeDPaymentDataProvider()['authFail1']['order'],
                'txType'          => InterPosResponseDataMapperTest::threeDPaymentDataProvider()['authFail1']['txType'],
                'request'         => Request::create('', 'POST', InterPosResponseDataMapperTest::threeDPaymentDataProvider()['authFail1']['threeDResponseData']),
                'paymentResponse' => InterPosResponseDataMapperTest::threeDPaymentDataProvider()['authFail1']['paymentData'],
                'expected'        => InterPosResponseDataMapperTest::threeDPaymentDataProvider()['authFail1']['expectedData'],
                'check_hash'      => false,
                'is3DSuccess'     => false,
                'isSuccess'       => false,
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
                'api_url' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
        ];
    }
}
