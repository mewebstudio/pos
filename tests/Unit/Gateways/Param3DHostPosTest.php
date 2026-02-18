<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper;
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
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ParamPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\Param3DHostPos
 * @covers \Mews\Pos\Gateways\AbstractHttpGateway
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class Param3DHostPosTest extends TestCase
{
    private ParamPosAccount $account;

    private array $config;

    /** @var Param3DHostPos */
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

    /** @var ParamPosRequestValueMapper & MockObject */
    private ParamPosRequestValueMapper $requestValueMapperMock;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'param-pos',
            'class'             => Param3DHostPos::class,
            'gateway_endpoints' => [
                'gateway_3d_host' => 'https://test-pos.param.com.tr/default.aspx',
            ],
        ];

        $this->account = AccountFactory::createParamPosAccount(
            'param-3d-host-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->requestValueMapperMock = $this->createMock(ParamPosRequestValueMapper::class);
        $this->requestMapperMock      = $this->createMock(Param3DHostPosRequestDataMapper::class);
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
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new Param3DHostPos(
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
        string  $txType,
        array   $requestData,
        ?string $gatewayUrl,
        string  $encodedRequestData,
        string  $responseData,
        array   $decodedResponseData,
        $formData
    ): void {
        $paymentModel = PosInterface::MODEL_3D_HOST;
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

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType);

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
        string $expectedExceptionClass,
        string $expectedExceptionMsg
    ): void {
        $card = $isWithCard ? $this->createMock(CreditCardInterface::class) : null;
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card);
    }

    public function testMake3DPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayment(
            Request::create(
                '',
                'POST',
            ),
            [],
            PosInterface::TX_TYPE_PAY_AUTH
        );
    }

    public function testMake3DPayPayment(): void
    {
        $request = Request::create('', 'POST');

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testMake3DHostPaymentHashMismatchException(): void
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

        $this->pos->make3DHostPayment($request, [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testMake3DHostPayment(): void
    {
        $responseData = ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'];
        $request      = Request::create(
            '',
            'POST',
            $responseData
        );
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $request->request->all())
            ->willReturn(true);

        $order  = ['id' => '123'];
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

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

    /**
     * @return void
     */
    public function testMake3DHostPaymentWithoutHashCheck(): void
    {
        $config = $this->config;
        $config += [
            'gateway_configs' => [
                'disable_3d_hash_check' => true,
            ],
        ];

        $pos = $this->createGateway($config);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $responseData = ParamPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'];
        $request = Request::create(
            '',
            'POST',
            $responseData
        );

        $order        = ['id' => '123'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($request->request->all(), $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos->make3DHostPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMakeRegularPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);

        $this->pos->makeRegularPayment(
            [],
            $this->createMock(CreditCardInterface::class),
            PosInterface::TX_TYPE_PAY_AUTH
        );
    }

    public function testMakeRegularPostAuthPayment(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->makeRegularPostPayment([]);
    }


    public function testStatusRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->status([]);
    }

    public function testCancelRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->cancel([]);
    }

    public function testRefundRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->refund([]);
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

    public function testCustomQueryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->customQuery([]);
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\Param3DHostPos ödeme altyapıda [pay] işlem tipi [3d_host] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d].',
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_host' => [
            'order'               => Param3DHostPosRequestDataMapperTest::threeDFormDataProvider()['3d_host_form_data']['order'],
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => ['request-data'],
            'gateway_url'         => 'https://test-pos.param.com.tr/default.aspx',
            'encodedRequestData'  => '<encoded-request-data>',
            'responseData'        => '<response-data>',
            'decodedResponseData' => Param3DHostPosRequestDataMapperTest::threeDFormDataProvider()['3d_host_form_data']['extra_data'],
            'formData'            => Param3DHostPosRequestDataMapperTest::threeDFormDataProvider()['3d_host_form_data']['expected'],
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
