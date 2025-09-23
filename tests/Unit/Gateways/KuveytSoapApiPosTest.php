<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\SoapClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\KuveytSoapApiPosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\KuveytSoapApiPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\KuveytSoapApiPos
 * @covers \Mews\Pos\Gateways\AbstractSoapGateway
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class KuveytSoapApiPosTest extends TestCase
{
    private KuveytPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var KuveytSoapApiPos */
    private PosInterface $pos;

    /** @var KuveytSoapApiPosRequestDataMapper & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var SoapClientInterface & MockObject */
    private MockObject $soapClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    private KuveytPosRequestValueMapper $requestValueMapper;

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'kuveyt-pos',
            'class'             => KuveytSoapApiPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ];

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '496',
            'apiuser1',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestValueMapper  = new KuveytPosRequestValueMapper();
        $this->requestMapperMock   = $this->createMock(KuveytSoapApiPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->soapClientMock      = $this->createMock(SoapClientInterface::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new KuveytSoapApiPos(
            $this->config,
            $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->eventDispatcherMock,
            $this->soapClientMock,
            $this->loggerMock,
        );

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertCount(count($this->requestValueMapper->getCurrencyMappings()), $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
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
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiURL(?string $txType, ?string $paymentModel, string $expected): void
    {
        $actual = $this->pos->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->get3DFormData(
            [],
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );
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

    public function testMakeRegularPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);

        $this->pos->makeRegularPayment(
            [],
            $this->card,
            PosInterface::TX_TYPE_PAY_AUTH
        );
    }

    public function testMakeRegularPostAuthPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->makeRegularPostPayment([]);
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
     * @dataProvider cancelDataProvider
     */
    public function testCancel(array $requestData, array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_CANCEL;
        $order   = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            $requestData['VPosMessage']['TransactionType'],
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->cancel($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testRefund(array $order, string $txType, array $requestData, array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account = $this->pos->getAccount();

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            $requestData['VPosMessage']['TransactionType'],
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->refund($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(array $requestData, array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_STATUS;
        $order   = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            $requestData['VPosMessage']['TransactionType'],
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $this->pos->status($order);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        $requestData = \iterator_to_array(KuveytSoapApiPosRequestDataMapperTest::createCancelRequestDataProvider());
        $responses   = \iterator_to_array(KuveytSoapApiPosResponseDataMapperTest::cancelTestDataProvider());
        return [
            'fail_1'    => [
                'request_data'  => $requestData[0]['expected'],
                'bank_response' => $responses['fail1']['responseData'],
                'expected_data' => $responses['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
            'success_1' => [
                'request_data'  => $requestData[0]['expected'],
                'bank_response' => $responses['success1']['responseData'],
                'expected_data' => $responses['success1']['expectedData'],
                'isSuccess'     => true,
            ],
        ];
    }

    public static function refundDataProvider(): array
    {
        $requestData = \iterator_to_array(KuveytSoapApiPosRequestDataMapperTest::createRefundRequestDataProvider());
        $responses   = \iterator_to_array(KuveytSoapApiPosResponseDataMapperTest::refundTestDataProvider());

        return [
            'fail_1' => [
                'order'         => [
                    'id'              => '2023070849CD',
                    'remote_order_id' => '114293600',
                    'ref_ret_num'     => '318923298433',
                    'auth_code'       => '241839',
                    'transaction_id'  => '298433',
                    'amount'          => 1.01,
                    'currency'        => PosInterface::CURRENCY_TRY,
                ],
                'txType'        => PosInterface::TX_TYPE_REFUND,
                'request_data'  => $requestData[0]['expected'],
                'bank_response' => $responses['fail1']['responseData'],
                'expected_data' => $responses['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
            'fail_2' => [
                'order'         => [
                    'id'              => '2023070849CD',
                    'remote_order_id' => '114293600',
                    'ref_ret_num'     => '318923298433',
                    'auth_code'       => '241839',
                    'transaction_id'  => '298433',
                    'amount'          => 1.01,
                    'order_amount'    => 2.01,
                    'currency'        => PosInterface::CURRENCY_TRY,
                ],
                'txType'        => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'request_data'  => $requestData[0]['expected'],
                'bank_response' => $responses['fail1']['responseData'],
                'expected_data' => $responses['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        $requestData = \iterator_to_array(KuveytSoapApiPosRequestDataMapperTest::createStatusRequestDataProvider())[0];
        $responses   = \iterator_to_array(KuveytSoapApiPosResponseDataMapperTest::statusTestDataProvider());

        yield [
            'request_data'  => $requestData['expected'],
            'bank_response' => $responses['fail1']['responseData'],
            'expected_data' => $responses['fail1']['expectedData'],
            'isSuccess'     => false,
        ];
        yield [
            'request_data'  => $requestData['expected'],
            'bank_response' => $responses['success1']['responseData'],
            'expected_data' => $responses['success1']['expectedData'],
            'isSuccess'     => true,
        ];
    }

    private function configureClientResponse(
        string $txType,
        string $apiUrl,
        string $soapAction,
        array  $requestData,
        array  $responseContent,
        array  $order,
        string $paymentModel
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->soapClientMock->expects(self::once())
            ->method('call')
            ->with(
                $apiUrl,
                $soapAction,
                $this->callback(function (array $actualData) use (&$updatedRequestDataPreparedEvent): bool {
                    $this->assertSame(
                        ['parameters' => ['request' => $updatedRequestDataPreparedEvent->getRequestData()]],
                        $actualData
                    );

                    return true;
                }),
            )
            ->willReturn($responseContent);

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
