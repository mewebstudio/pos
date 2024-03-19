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
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use Mews\Pos\Factory\SerializerFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\PosNetResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\PosNet
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
        $this->expectException(Exception::class);
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(
            PosNet::class,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class)
        );
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(PosNet::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['getOosTransactionData'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('getOosTransactionData')->willReturn($this->getSampleOoTransactionFailResponseData());

        $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_AUTH, $this->card);
    }


    /**`
     * @return void
     *
     * @throws Exception
     */
    public function testMake3DPaymentSuccess(): void
    {
        $responseMapperTest = new PosNetResponseDataMapperTest();
        $request            = Request::create('', 'POST', [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ]);
        $crypt              = CryptFactory::createGatewayCrypt(PosNet::class, new NullLogger());
        $requestMapper      = RequestDataMapperFactory::createGatewayRequestMapper(PosNet::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper     = ResponseDataMapperFactory::createGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());
        $serializer         = SerializerFactory::createGatewaySerializer(PosNet::class);

        $this->order['id'] = '80603153823';
        $posMock           = $this->getMockBuilder(PosNet::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();
        $posMock->setTestMode(true);

        $bankResponses = $responseMapperTest->threeDPaymentDataProvider()['success1'];
        $posMock->expects($this->exactly(2))->method('send')->will(
            $this->onConsecutiveCalls(
                $bankResponses['threeDResponseData'],
                $bankResponses['paymentData']
            )
        );

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);
        $resp = $posMock->getResponse();
        unset($resp['transaction_time'], $bankResponses['expectedData']['transaction_time']);
        unset($resp['all'], $resp['3d_all']);
        \ksort($bankResponses['expectedData']);
        \ksort($resp);
        $this->assertSame($bankResponses['expectedData'], $resp);
    }

    /**
     * @return string[]
     */
    private function getSampleOoTransactionFailResponseData(): array
    {
        return [
            'approved' => '0',
            'respCode' => '0003',
            'respText' => '148 MID,TID,IP HATALI:89.244.149.137',
        ];
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
        bool    $checkHash,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void
    {
        if ($checkHash) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $resolveResponse['oosResolveMerchantDataResponse'])
                ->willReturn(true);
        }

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

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {

            $this->serializerMock->expects(self::once())
                ->method('encode')
                ->with($resolveMerchantRequestData, $txType)
                ->willReturn('resolveMerchantRequestData-body');

            $this->serializerMock->expects(self::once())
                ->method('decode')
                ->with('resolveMerchantRequestData-body', $txType)
                ->willReturn($resolveResponse);

            $this->prepareClient(
                $this->httpClientMock,
                'resolveMerchantRequestData-body',
                $this->config['gateway_endpoints']['payment_api'],
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body'    => \sprintf('xmldata=%s', 'resolveMerchantRequestData-body'),
                ],
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

    public static function make3DPaymentDataProvider(): array
    {
        $resolveMerchantResponseData = [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ];

        return [
            'auth_fail' => [
                'order'           => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['order'],
                'txType'          => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['txType'],
                'request'         => Request::create('', 'POST', $resolveMerchantResponseData),
                'resolveResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['threeDResponseData'],
                'paymentResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['paymentData'],
                'expected'        => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['auth_fail1']['expectedData'],
                'check_hash'      => false,
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
                'check_hash'      => false,
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'   => [
                'order'           => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create('', 'POST', $resolveMerchantResponseData),
                'resolveResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
                'paymentResponse' => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => PosNetResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'check_hash'      => true,
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }
}
