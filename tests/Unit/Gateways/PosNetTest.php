<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Exception;
use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\PosNetResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\PosNet
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class PosNetTest extends TestCase
{
    use HttpClientTestTrait;

    private PosNetAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var PosNet */
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
            'name'              => 'Yapıkredi',
            'class'             => PosNet::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway_3d'  => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ],
        ];

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->order = [
            'id'          => 'YKB_TST_190620093100_024',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestMapperMock   = $this->createMock(PosNetRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(PosNetResponseDataMapper::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new PosNet(
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

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '21', '12', '122', 'ahmet');
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
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataOosTransactionFail(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['request-data'];
        $order       = $this->order;
        $this->expectException(Exception::class);
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order, $txType, $this->card)
            ->willReturn($requestData);

        $responseData = [
            'approved' => '0',
            'respCode' => '0003',
            'respText' => '148 MID,TID,IP HATALI:89.244.149.137',
        ];

        $this->configureClientResponse(
            $txType,
            $this->config['gateway_endpoints']['payment_api'],
            $requestData,
            'request-body',
            'response-body',
            $responseData,
            $order,
            PosInterface::MODEL_3D_SECURE
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->pos->get3DFormData($order, PosInterface::MODEL_3D_SECURE, $txType, $this->card);
    }

    /**
     * @dataProvider make3DPaymentDataProvider
     */
    public function testMake3DPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $resolveResponse,
        array   $paymentResponse,
        array   $expectedResponse,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void
    {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $resolveResponse['oosResolveMerchantDataResponse'])
                ->willReturn(true);
        }

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($resolveResponse)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);


        $resolveMerchantRequestData = [
            'resolveMerchantRequestData',
        ];
        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DResolveMerchantRequestData')
            ->with($this->account, $order, $request->request->all())
            ->willReturn($resolveMerchantRequestData);


        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $request->request->all())
                ->willReturn($create3DPaymentRequestData);

            $this->serializerMock->expects(self::exactly(2))
                ->method('encode')
                ->willReturnMap([
                    [
                        $resolveMerchantRequestData,
                        $txType,
                        'resolveMerchantRequestData-body',
                    ],
                    [
                        $create3DPaymentRequestData,
                        $txType,
                        'payment-request-body',
                    ],
                ]);

            $this->serializerMock->expects(self::exactly(2))
                ->method('decode')
                ->willReturnMap([
                    [
                        'resolveMerchantRequestData-body',
                        $txType,
                        $resolveResponse,
                    ],
                    [
                        'response-body-2',
                        $txType,
                        $paymentResponse,
                    ],
                ]);

            $this->prepareHttpClientRequestMulti(
                $this->httpClientMock,
                [
                    'resolveMerchantRequestData-body',
                    'response-body-2',
                ],
                [
                    $this->config['gateway_endpoints']['payment_api'],
                    $this->config['gateway_endpoints']['payment_api'],
                ],
                [
                    [
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        'body'    => \sprintf('xmldata=%s', 'resolveMerchantRequestData-body'),
                    ],
                    [
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        'body'    => \sprintf('xmldata=%s', 'payment-request-body'),
                    ],
                ]
            );

            $paymentModel = PosInterface::MODEL_3D_SECURE;
            $this->eventDispatcherMock->expects(self::exactly(2))
                ->method('dispatch')
                // could not find another way expect using deprecated withConsecutive() function
                ->withConsecutive(
                    [$this->callback(fn($dispatchedEvent): bool => $dispatchedEvent instanceof RequestDataPreparedEvent
                        && get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $resolveMerchantRequestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel())],
                    [$this->callback(fn($dispatchedEvent): bool => $dispatchedEvent instanceof RequestDataPreparedEvent
                        && get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $create3DPaymentRequestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel())]
                );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->configureClientResponse(
                $txType,
                $this->config['gateway_endpoints']['payment_api'],
                $resolveMerchantRequestData,
                'resolveMerchantRequestData-body',
                'resolveMerchantRequestData-body',
                $resolveResponse,
                $order,
                PosInterface::MODEL_3D_SECURE
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, null, $txType, $order)
                ->willReturn($expectedResponse);

            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
        }

        $this->pos->make3DPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DHostPayment(): void
    {
        $request = Request::create('', 'POST');

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DHostPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
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

    public static function make3DPaymentDataProvider(): array
    {
        $resolveMerchantResponseData = [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ];

        return [
            'auth_fail'      => [
                'order'           => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['order'],
                'txType'          => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['txType'],
                'request'         => Request::create('', 'POST', $resolveMerchantResponseData),
                'resolveResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['threeDResponseData'],
                'paymentResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['paymentData'],
                'expected'        => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'fail2-md-empty' => [
                'order'           => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['fail2-md-empty']['order'],
                'txType'          => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['fail2-md-empty']['txType'],
                'request'         => Request::create('', 'POST', $resolveMerchantResponseData),
                'resolveResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['fail2-md-empty']['threeDResponseData'],
                'paymentResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['fail2-md-empty']['paymentData'],
                'expected'        => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['fail2-md-empty']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'        => [
                'order'           => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create('', 'POST', $resolveMerchantResponseData),
                'resolveResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
                'paymentResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
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
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
            ],
            [
                'order'   => [
                    'id' => '2020110828BC',
                ],
                'txType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
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
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
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
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
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
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
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
                'api_url' => 'https://setmpos.ykb.com/PosnetWebService/XML',
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
        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($requestData, $txType)
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
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => \sprintf('xmldata=%s', $encodedRequestData),
            ],
        );

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(fn($dispatchedEvent): bool => $dispatchedEvent instanceof RequestDataPreparedEvent
                && get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $requestData === $dispatchedEvent->getRequestData()
                && $order === $dispatchedEvent->getOrder()
                && $paymentModel === $dispatchedEvent->getPaymentModel()));
    }
}
