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

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $statusRequestData = [
            'statusRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->willReturn($statusRequestData);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            [
                'body' => 'request-body',
            ],
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($statusRequestData, PosInterface::TX_TYPE_STATUS)
            ->willReturn('request-body');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', PosInterface::TX_TYPE_STATUS)
            ->willReturn($bankResponse);

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->status($this->order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider orderHistoryDataProvider
     */
    public function testOrderHistory(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $historyRequestData = [
            'historyRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->willReturn($historyRequestData);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            [
                'body' => 'request-body',
            ],
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($historyRequestData, PosInterface::TX_TYPE_ORDER_HISTORY)
            ->willReturn('request-body');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', PosInterface::TX_TYPE_ORDER_HISTORY)
            ->willReturn($bankResponse);

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);


        $this->pos->orderHistory($this->order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testCancel(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $cancelRequestData = [
            'cancelRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->willReturn($cancelRequestData);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            [
                'body' => 'request-body',
            ],
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($cancelRequestData, PosInterface::TX_TYPE_CANCEL)
            ->willReturn('request-body');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', PosInterface::TX_TYPE_CANCEL)
            ->willReturn($bankResponse);

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->cancel($this->order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testRefund(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $refundRequestData = [
            'refundRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->willReturn($refundRequestData);

        $this->prepareClient(
            $this->httpClientMock,
            'response-body',
            'https://entegrasyon.asseco-see.com.tr/fim/api',
            [
                'body' => 'request-body',
            ],
        );

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($refundRequestData, PosInterface::TX_TYPE_REFUND)
            ->willReturn('request-body');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-body', PosInterface::TX_TYPE_REFUND)
            ->willReturn($bankResponse);

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->refund($this->order);

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
        bool    $checkHash,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void
    {
        if ($checkHash) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $request->request->all())
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

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData']),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'check_hash'      => false,
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['txType'],
                'request'         => Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['threeDResponseData']),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail']['expectedData'],
                'check_hash'      => true,
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']),
                'paymentResponse' => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => EstPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'check_hash'      => true,
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return [
            'fail_1'    => [
                'bank_response' => EstPosResponseDataMapperTest::cancelTestDataProvider()['fail1']['responseData'],
                'expected_data' => EstPosResponseDataMapperTest::cancelTestDataProvider()['fail1']['expectedData'],
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
}
